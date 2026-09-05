<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\Notification;
use App\Models\RateLimiter;
use App\Models\User;
use App\Services\Auth;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Full port of backend/Controllers/Shared/AuthController.php. CSRF is
 * enforced by the RejectCrossOriginPost middleware (see bootstrap/app.php)
 * instead of the original's explicit Auth::verifyCsrf() token check, so
 * those calls are dropped here — same protection, applied once at the
 * middleware layer instead of per-action.
 */
class AuthController extends Controller
{
    private MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }

    public function loginPage()
    {
        return view('auth.login', ['csrfToken' => \App\Core\Auth::csrfToken()]);
    }

    public function login(Request $request): JsonResponse
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');
        $userModel = new User();
        $loginLog = new LoginLog();

        if ($loginLog->isBruteForced($email)) {
            return response()->json(['success' => false, 'message' => 'Too many failed attempts. Please try again in 5 minutes.']);
        }

        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $loginLog->log($email, 'failed', null, 'Invalid credentials');

            return response()->json(['success' => false, 'message' => 'Invalid email or password.']);
        }

        if ($user['status'] !== 'Active') {
            $loginLog->log($email, 'failed', $user['user_id'], 'Account not active');

            return response()->json(['success' => false, 'message' => 'Account not active.']);
        }

        $require2fa = (bool) $user['two_factor_enabled'];
        if (!$user['last_login']) {
            $require2fa = true;
        } else {
            $days = (new \DateTime())->diff(new \DateTime($user['last_login']))->days;
            if ($days > 30) {
                $require2fa = true;
            }
        }

        if ($require2fa) {
            $rateLimiter = new RateLimiter();
            if ($rateLimiter->tooManyAttempts('2fa_send', (string) $user['user_id'], 3, 600)) {
                $loginLog->log($email, 'failed', $user['user_id'], 'Too many 2FA code requests');

                return response()->json(['success' => false, 'message' => 'Too many verification code requests. Please wait a few minutes and try again.']);
            }
            $rateLimiter->hit('2fa_send', (string) $user['user_id']);

            $code = sprintf('%06d', random_int(0, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $userModel->setTwoFactorCode($user['user_id'], $code, $expires);

            $sent = $this->mailService->send(
                $user['email'],
                $user['email'],
                'Your LSPU Security Verification Code',
                $this->twoFactorEmailBody($code),
                "Your LSPU EIS verification code is: {$code}\nThis code will expire in 10 minutes."
            );

            if (!$sent) {
                $loginLog->log($email, 'failed', $user['user_id'], 'Failed to send 2FA email');

                return response()->json(['success' => false, 'message' => 'Failed to send verification email. Please try again.']);
            }

            Session::put('temp_user_id', $user['user_id']);
            Session::put('temp_email', $user['email']);
            Session::put('temp_user_role', $user['user_role']);
            Session::put('temp_campus_id', $user['campus_id']);
            Session::put('2fa_required', true);
            Session::put('2fa_method', 'email');

            $reason = $user['two_factor_enabled'] ? 'User-enabled 2FA' : '2FA required due to inactive login';
            $loginLog->log($email, '2fa_required', $user['user_id'], $reason);

            return response()->json([
                'success' => true,
                'requires_2fa' => true,
                'message' => 'Verification code sent to your email.',
            ]);
        }

        $userModel->updateLastLogin($user['user_id']);
        Auth::login($user);
        $loginLog->log($email, 'success', $user['user_id']);

        return response()->json(['success' => true, 'redirect' => '/'.$this->redirectForRole($user['user_role'])]);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        if (!Session::get('2fa_required')) {
            return response()->json(['success' => false, 'message' => '2FA not required.']);
        }

        $code = (string) $request->input('verification_code', '');
        $userId = Session::get('temp_user_id', 0);

        if (empty($code) || !$userId) {
            return response()->json(['success' => false, 'message' => 'Invalid request.']);
        }

        $userModel = new User();
        $loginLog = new LoginLog();
        $rateLimiter = new RateLimiter();

        if ($rateLimiter->tooManyAttempts('2fa_verify', (string) $userId, 5, 600)) {
            $loginLog->log(Session::get('temp_email', ''), 'failed', $userId, 'Too many 2FA verification attempts');
            Session::forget(['2fa_required', 'temp_user_id', 'temp_email', 'temp_user_role', 'temp_campus_id', '2fa_method']);

            return response()->json(['success' => false, 'message' => 'Too many attempts. Please log in again to receive a new code.']);
        }
        $rateLimiter->hit('2fa_verify', (string) $userId);

        $stored = $userModel->getTwoFactorCode($userId);

        $valid = $stored && hash_equals($stored['two_factor_code'], $code) && date('Y-m-d H:i:s') < $stored['two_factor_expires'];

        if (!$valid) {
            $loginLog->log(Session::get('temp_email', ''), 'failed', $userId, 'Invalid 2FA code');

            return response()->json(['success' => false, 'message' => 'Invalid or expired verification code.']);
        }

        $rateLimiter->clear('2fa_verify', (string) $userId);
        $userModel->clearTwoFactorCode($userId);

        $tempEmail = Session::get('temp_email');
        $tempRole = Session::get('temp_user_role');

        Auth::login([
            'user_id' => $userId,
            'email' => $tempEmail,
            'user_role' => $tempRole,
            'campus_id' => Session::get('temp_campus_id'),
        ]);
        Session::forget(['2fa_required', 'temp_user_id', 'temp_email', 'temp_user_role', 'temp_campus_id', '2fa_method']);

        $userModel->updateLastLogin($userId);
        $loginLog->log($tempEmail, 'success', $userId);

        return response()->json(['success' => true, 'redirect' => $this->redirectForRole($tempRole)]);
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();

        return redirect('/login');
    }

    public function forgotPasswordPage()
    {
        return view('auth.forgot_password');
    }

    public function sendResetLink(Request $request): JsonResponse
    {
        $email = trim((string) $request->input('email', ''));
        $ip = $request->ip() ?? '';

        $rateLimiter = new RateLimiter();
        if ($rateLimiter->tooManyAttempts('password_reset_ip', $ip, 5, 900)
            || ($email !== '' && $rateLimiter->tooManyAttempts('password_reset_email', $email, 3, 900))) {
            return response()->json(['success' => false, 'message' => 'Too many reset requests. Please try again in 15 minutes.']);
        }
        $rateLimiter->hit('password_reset_ip', $ip);
        if ($email !== '') {
            $rateLimiter->hit('password_reset_email', $email);
        }

        $userModel = new User();
        $user = $userModel->findByEmailOrSecondary($email);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $userModel->setResetToken($user['user_id'], $token, $expiry);

            $resetLink = config('app.url').'/reset_password?token='.urlencode($token).'&email='.urlencode($user['email']);
            $this->mailService->send(
                $user['email'],
                $user['email'],
                'Reset Your LSPU EIS Password',
                $this->resetLinkEmailBody($resetLink),
                "Reset your password: {$resetLink}\nIf you did not request this, ignore this email."
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'If that email address is registered, a password reset link has been sent.',
        ]);
    }

    public function resetPasswordPage(Request $request)
    {
        $token = (string) $request->query('token', '');
        $email = (string) $request->query('email', '');
        $showForm = false;

        if ($token && $email) {
            $user = (new User())->findByResetToken($email, $token);
            $showForm = (bool) ($user && $user['reset_token'] && strtotime($user['reset_token_expiry']) > time());
        }

        return view('auth.reset_password', [
            'token' => $token,
            'email' => $email,
            'showForm' => $showForm,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');
        $password2 = (string) $request->input('password2', '');

        if ($password !== $password2) {
            return response()->json(['success' => false, 'message' => 'Passwords do not match.']);
        }

        $userModel = new User();
        $user = $userModel->findByResetToken($email, $token);

        if (!$user || !$user['reset_token'] || strtotime($user['reset_token_expiry']) <= time()) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token.']);
        }

        $userModel->updatePassword($user['user_id'], password_hash($password, PASSWORD_DEFAULT));

        Session::put('user_id', $user['user_id']);
        Session::put('email', $email);

        (new Notification())->create($user['user_id'], 'password', 'Your password was changed.', 'If you did not perform this action, please contact support.');

        $this->mailService->send(
            $email,
            $email,
            'Your LSPU EIS Password Was Reset',
            MailService::wrap(
                'Password Successfully Changed',
                '<p>Your password has been successfully changed. You can now log in with your new password.</p>'
                    .'<p>If you did not perform this action, please contact support immediately.</p>',
                'Login to LSPU EIS',
                config('app.url').'/login'
            ),
            'Your password was reset. If you did not perform this, contact support.'
        );

        return response()->json(['success' => true, 'message' => 'Password successfully changed.']);
    }

    private function redirectForRole(string $role): string
    {
        return match ($role) {
            'admin' => 'admin_dashboard',
            'superadmin' => 'superadmin_dashboard',
            'alumni' => 'home',
            'employer' => 'employer_dashboard',
            default => 'login',
        };
    }

    private function twoFactorEmailBody(string $code): string
    {
        return MailService::wrap(
            'Security Verification Required',
            '<p>Use the following verification code to complete your login:</p>'
                .'<div style="background:#f1f5f9;border-radius:8px;padding:18px;margin:16px 0;text-align:center;">'
                ."<span style=\"font-size:32px;font-weight:bold;letter-spacing:6px;color:#00A0E9;\">{$code}</span>"
                .'</div>'
                .'<p>This code will expire in 10 minutes. Never share it with anyone.</p>'
        );
    }

    private function resetLinkEmailBody(string $resetLink): string
    {
        return MailService::wrap(
            'Password Reset Request',
            '<p>We received a request to reset your password. This link will expire in 1 hour.</p>'
                .'<p>If you did not request this, you can safely ignore this email.</p>',
            'Reset Password',
            $resetLink
        );
    }
}
