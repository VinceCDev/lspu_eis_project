<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ConvertsUploadedFile;
use App\Core\Uploader;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\User;
use App\Services\Auth;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Admin/UserController.php. */
class UserController extends Controller
{
    use ConvertsUploadedFile;

    public function index()
    {
        return view('admin.user', [
            'title' => 'Accounts | LSPU - EIS',
            'active' => Auth::role() === 'superadmin' ? 'superadmin_user' : 'admin_user',
            'pageCss' => 'admin_accounts.css',
            'pageJs' => 'admin_user.js',
        ]);
    }

    public function list(): JsonResponse
    {
        $isSuperadmin = Auth::role() === 'superadmin';
        $campusId = $isSuperadmin ? null : Auth::campusId();

        return response()->json(['success' => true, 'accounts' => (new Account())->allAccounts($campusId, $isSuperadmin)]);
    }

    public function campuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'campuses' => (new Campus())->all(),
            'is_superadmin' => Auth::role() === 'superadmin',
            'current_campus_id' => Auth::campusId(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $role = $request->input('user_role', '');
        $email = $request->input('email', '');
        $status = $request->input('status', 'Active');

        if (!in_array($role, ['admin', 'employer', 'alumni'], true) || $email === '') {
            return response()->json(['success' => false, 'message' => 'Invalid role or missing email.']);
        }

        if ($role === 'employer' && Auth::role() !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Employer accounts are managed by superadmin only.']);
        }

        $adminCampusId = null;
        if ($role === 'admin') {
            $adminCampusId = Auth::role() === 'superadmin'
                ? (int) $request->input('campus_id', 0)
                : Auth::campusId();
            if (!$adminCampusId) {
                return response()->json(['success' => false, 'message' => 'Campus is required for admin accounts.']);
            }
        }

        $userModel = new User();
        if ($userModel->emailExists($email)) {
            return response()->json(['success' => false, 'message' => 'Email already registered.']);
        }

        $profilePic = null;
        if ($request->hasFile('profile_pic')) {
            $category = $role === 'employer' ? 'logos' : 'profile_picture';
            $filename = Uploader::store($this->fileToArray($request->file('profile_pic')), $category);
            $profilePic = $filename ? "uploads/{$category}/{$filename}" : null;
        }

        $randomPassword = bin2hex(random_bytes(5));
        $userId = $userModel->create($email, null, password_hash($randomPassword, PASSWORD_DEFAULT), $role, $status);

        $accountModel = new Account();
        $recipientName = $email;

        if ($role === 'admin') {
            $first = $request->input('first_name', '');
            $last = $request->input('last_name', '');
            $accountModel->createAdmin($userId, $first, $request->input('middle_name') ?? '', $last, $profilePic, $adminCampusId);
            $recipientName = trim("$first $last");
        } elseif ($role === 'employer') {
            $company = $request->input('company_name', '');
            $accountModel->createEmployer($userId, $company, $request->input('industry_type', ''), $profilePic);
            $recipientName = $company;
        } else {
            $first = $request->input('first_name', '');
            $last = $request->input('last_name', '');
            $accountModel->createAlumni($userId, $first, $request->input('middle_name') ?? '', $last, $profilePic);
            $recipientName = trim("$first $last");
        }

        (new MailService())->send(
            $email,
            $recipientName,
            'Your LSPU EIS Account Credentials',
            MailService::wrap(
                'Welcome, '.htmlspecialchars($recipientName).'!',
                '<p>Your account has been created successfully.</p>'
                    .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
                    ."<p style=\"margin:0 0 6px;\"><strong>Email:</strong> {$email}</p>"
                    ."<p style=\"margin:0;\"><strong>Password:</strong> {$randomPassword}</p>"
                    .'</div>'
                    .'<p>For security, please change your password after your first login.</p>',
                'Login to LSPU EIS',
                config('app.url').'/login'
            )
        );

        (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'create_account', $role, $userId, "Created {$role} account for {$recipientName}.");

        return response()->json(['success' => true, 'message' => 'Account created successfully.']);
    }

    public function update(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        $role = $request->input('user_role', '');
        $status = $request->input('status', '');

        if (!$userId || !in_array($role, ['admin', 'employer', 'alumni'], true)) {
            return response()->json(['success' => false, 'message' => 'Missing user_id or user_role.']);
        }

        if ($role === 'employer' && Auth::role() !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Employer accounts are managed by superadmin only.']);
        }

        $accountModel = new Account();

        if ($role === 'admin') {
            if (!Auth::sameCampus($accountModel->adminCampusId($userId))) {
                return response()->json(['success' => false, 'message' => 'Forbidden: resource belongs to a different campus.'], 403);
            }

            $profilePic = null;
            if ($request->hasFile('profile_pic')) {
                $filename = Uploader::store($this->fileToArray($request->file('profile_pic')), 'profile_picture');
                $profilePic = $filename ? 'uploads/profile_picture/'.$filename : null;
            }
            $campusId = Auth::role() === 'superadmin' && $request->has('campus_id')
                ? (int) $request->input('campus_id')
                : null;
            $accountModel->updateAdmin(
                $userId,
                trim($request->input('email', '')),
                trim($request->input('first_name', '')),
                trim($request->input('middle_name', '')),
                trim($request->input('last_name', '')),
                $status,
                $profilePic,
                $campusId
            );
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'update_account', 'admin', $userId, 'Updated admin account.');

            return response()->json(['success' => true, 'message' => 'Admin updated successfully.']);
        }

        if ($role === 'employer') {
            $companyLogo = null;
            if ($request->hasFile('profile_pic')) {
                $filename = Uploader::store($this->fileToArray($request->file('profile_pic')), 'logos');
                $companyLogo = $filename ? 'uploads/logos/'.$filename : null;
            }
            $accountModel->updateEmployer(
                $userId,
                trim($request->input('company_name', '')),
                trim($request->input('industry_type', '')),
                $status,
                $companyLogo
            );
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'update_account', 'employer', $userId, 'Updated employer account.');

            return response()->json(['success' => true, 'message' => 'Employer updated successfully.']);
        }

        if (!Auth::sameCampus($accountModel->alumniCampusId($userId))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: resource belongs to a different campus.'], 403);
        }

        $profilePic = null;
        if ($request->hasFile('profile_pic')) {
            $filename = Uploader::store($this->fileToArray($request->file('profile_pic')), 'profile_picture');
            $profilePic = $filename ? 'uploads/profile_picture/'.$filename : null;
        }
        $accountModel->updateAlumni(
            $userId,
            trim($request->input('email', '')),
            trim($request->input('first_name', '')),
            trim($request->input('middle_name', '')),
            trim($request->input('last_name', '')),
            $status,
            $profilePic
        );
        (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'update_account', 'alumni', $userId, 'Updated alumni account.');

        return response()->json(['success' => true, 'message' => 'Alumni updated successfully.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        $role = $request->input('user_role', '');

        if (!$userId || !$role) {
            return response()->json(['success' => false, 'message' => 'Missing user_id or user_role.']);
        }

        if ($role === 'employer' && Auth::role() !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Employer accounts are managed by superadmin only.']);
        }

        $accountModel = new Account();
        if ($role === 'admin' && !Auth::sameCampus($accountModel->adminCampusId($userId))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: resource belongs to a different campus.'], 403);
        }
        if ($role === 'alumni' && !Auth::sameCampus($accountModel->alumniCampusId($userId))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: resource belongs to a different campus.'], 403);
        }

        $ok = $accountModel->delete($userId, $role);
        if ($ok) {
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'delete_account', $role, $userId, "Deleted {$role} account.");
        }

        return response()->json(['success' => $ok, 'message' => $ok ? 'Account deleted successfully.' : 'Failed to delete account.']);
    }

    /** Sets an account to Inactive with a required reason, logged to Audit Logs. */
    public function deactivate(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        $role = $request->input('user_role', '');
        $notes = trim($request->input('notes', ''));

        if (!$userId || !$role) {
            return response()->json(['success' => false, 'message' => 'Missing user_id or user_role.']);
        }

        if ($notes === '') {
            return response()->json(['success' => false, 'message' => 'Please provide a reason for deactivating this account.']);
        }

        if ($role === 'employer' && Auth::role() !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Employer accounts are managed by superadmin only.']);
        }

        $accountModel = new Account();
        if ($role === 'admin' && !Auth::sameCampus($accountModel->adminCampusId($userId))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: resource belongs to a different campus.'], 403);
        }
        if ($role === 'alumni' && !Auth::sameCampus($accountModel->alumniCampusId($userId))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: resource belongs to a different campus.'], 403);
        }

        $ok = $accountModel->setStatus($userId, $role, 'Inactive');
        if ($ok) {
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'deactivate_account', $role, $userId, "Deactivated {$role} account. Reason: {$notes}");

            $email = (new User())->findEmailById($userId);
            if ($email) {
                (new MailService())->send(
                    $email,
                    $email,
                    'Your LSPU EIS Account Has Been Deactivated',
                    MailService::wrap(
                        'Account Deactivated',
                        '<p>Your LSPU EIS account has been deactivated by an administrator and you will not be able to log in until it is reactivated.</p>'
                            .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
                            .'<p style="margin:0;"><strong>Reason:</strong> '.htmlspecialchars($notes).'</p>'
                            .'</div>'
                            .'<p>If you believe this was a mistake, contact your Alumni Affairs and Placement Services Office.</p>'
                    ),
                    "Your LSPU EIS account has been deactivated.\nReason: {$notes}\nIf you believe this was a mistake, contact your Alumni Affairs and Placement Services Office."
                );
            }
        }

        return response()->json(['success' => $ok, 'message' => $ok ? 'Account deactivated.' : 'Failed to deactivate account.']);
    }

    /** Admin-triggered password reset — same token/email mechanism as the self-service "Forgot Password" flow. */
    public function resetPassword(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        $role = $request->input('user_role', '');

        if (!$userId || !$role) {
            return response()->json(['success' => false, 'message' => 'Missing user_id or user_role.']);
        }

        if ($role === 'employer' && Auth::role() !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Employer accounts are managed by superadmin only.']);
        }

        $accountModel = new Account();
        if ($role === 'admin' && !Auth::sameCampus($accountModel->adminCampusId($userId))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: resource belongs to a different campus.'], 403);
        }
        if ($role === 'alumni' && !Auth::sameCampus($accountModel->alumniCampusId($userId))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: resource belongs to a different campus.'], 403);
        }

        $userModel = new User();
        $email = $userModel->findEmailById($userId);
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Account not found.']);
        }

        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $userModel->setResetToken($userId, $token, $expiry);

        $resetLink = config('app.url').'/reset_password?token='.urlencode($token).'&email='.urlencode($email);
        (new MailService())->send(
            $email,
            $email,
            'Reset Your LSPU EIS Password',
            MailService::wrap(
                'Password Reset Requested',
                '<p>An administrator has initiated a password reset for your LSPU EIS account. This link will expire in 1 hour.</p>'
                    .'<p>If you were not expecting this, contact your Alumni Affairs and Placement Services Office.</p>',
                'Reset Password',
                $resetLink
            ),
            "Reset your password: {$resetLink}\nIf you were not expecting this, contact your Alumni Affairs and Placement Services Office."
        );

        (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'admin_reset_password', $role, $userId, 'Sent password reset link.');

        return response()->json(['success' => true, 'message' => 'Password reset link sent to '.$email.'.']);
    }
}
