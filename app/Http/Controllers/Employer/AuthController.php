<?php

namespace App\Http\Controllers\Employer;

use App\Concerns\ConvertsUploadedFile;
use App\Core\Uploader;
use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Notification;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Ported from backend/Controllers/Employer/AuthController.php. */
class AuthController extends Controller
{
    use ConvertsUploadedFile;

    private const REQUIRED_FIELDS = [
        'email', 'password', 'current_password', 'company_name', 'company_location',
        'contact_email', 'contact_number', 'industry_type', 'nature_of_business',
        'tin', 'date_established', 'company_type', 'accreditation_status',
    ];

    public function signupPage()
    {
        return view('auth.employer_signup', ['csrfToken' => \App\Core\Auth::csrfToken()]);
    }

    public function loginPage(): RedirectResponse
    {
        // Employer sign-in uses the same unified login flow as admin/alumni,
        // which already redirects by role.
        return redirect('/login');
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $missing = array_filter(self::REQUIRED_FIELDS, fn ($field) => !$request->filled($field));
            if ($missing) {
                throw new \RuntimeException('Missing required fields: '.implode(', ', $missing));
            }

            if ($request->input('agree_to_disclaimer') !== '1') {
                throw new \RuntimeException('You must agree to the Employer Terms & Data Privacy Policy to register.');
            }

            $email = trim($request->input('email'));
            $contactEmail = trim($request->input('contact_email'));
            $password = $request->input('password');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid email format');
            }

            if ($password !== $request->input('current_password')) {
                throw new \RuntimeException('Passwords do not match');
            }

            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
                throw new \RuntimeException('Password does not meet the policy requirements');
            }

            $userModel = new User();
            if ($userModel->emailExists($email)) {
                throw new \RuntimeException('Email already registered');
            }

            $companyLogo = '';
            if ($request->hasFile('company_logo')) {
                $companyLogo = Uploader::storeNamed($this->fileToArray($request->file('company_logo')), 'logos', ['image/jpeg', 'image/png']) ?? '';
                if ($companyLogo === '') {
                    throw new \RuntimeException('Invalid logo file type. Only JPG and PNG are allowed.');
                }
            }

            $documentFile = Uploader::storeNamed($this->fileToArray($request->file('document_file')), 'documents', ['image/jpeg', 'image/png', 'application/pdf']);
            if (!$documentFile) {
                throw new \RuntimeException('Document file is required (JPG, PNG, or PDF).');
            }

            $companyName = trim($request->input('company_name'));

            try {
                DB::transaction(function () use ($userModel, $email, $password, $companyName, $request, $companyLogo, $contactEmail, $documentFile) {
                    $userId = $userModel->create($email, null, password_hash($password, PASSWORD_DEFAULT), 'employer', 'Pending', true);

                    (new Employer())->register($userId, [
                        'company_name' => $companyName,
                        'company_logo' => $companyLogo,
                        'company_location' => trim($request->input('company_location')),
                        'contact_email' => $contactEmail,
                        'contact_number' => trim($request->input('contact_number')),
                        'industry_type' => trim($request->input('industry_type')),
                        'nature_of_business' => trim($request->input('nature_of_business')),
                        'tin' => trim($request->input('tin')),
                        'date_established' => $request->input('date_established'),
                        'company_type' => trim($request->input('company_type')),
                        'accreditation_status' => trim($request->input('accreditation_status')),
                        'document_file' => $documentFile,
                    ]);
                });
            } catch (\Throwable $e) {
                throw new \RuntimeException('Registration failed: '.$e->getMessage());
            }

            (new MailService())->send(
                $email,
                $companyName,
                'Welcome to LSPU EIS Employer Portal',
                $this->welcomeEmailBody($companyName)
            );

            $notification = new Notification();
            $recipientIds = array_merge($userModel->allIdsByRole('admin'), $userModel->allIdsByRole('superadmin'));
            foreach ($recipientIds as $adminId) {
                $notification->create($adminId, 'registration', 'New employer registration pending approval', "{$companyName} has registered and is awaiting approval.");
            }

            return response()->json(['success' => true, 'message' => 'Registration successful! Please wait for admin approval. Check your email for confirmation.']);
        } catch (\RuntimeException $e) {
            error_log('Employer registration error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function welcomeEmailBody(string $companyName): string
    {
        $safeName = htmlspecialchars($companyName);

        return MailService::wrap(
            "Welcome, {$safeName}!",
            '<p>Your employer account has been created successfully and is pending approval by the admin.</p>'
                .'<p>We will review your documents and notify you once your account is approved.</p>',
            'Login to LSPU EIS',
            config('app.url').'/login'
        );
    }
}
