<?php

namespace App\Http\Controllers\Alumni;

use App\Core\Uploader;
use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Campus;
use App\Models\Notification;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Alumni/AuthController.php. */
class AuthController extends Controller
{
    private const REQUIRED_FIELDS = [
        'email', 'password', 'current_password', 'first_name', 'last_name', 'birthdate',
        'contact', 'gender', 'civil_status', 'city', 'province', 'year_graduated', 'college', 'course', 'campus_id',
    ];

    public function signupPage()
    {
        return view('auth.signup');
    }

    public function campuses(): JsonResponse
    {
        return response()->json(['success' => true, 'campuses' => (new Campus())->all()]);
    }

    public function register(Request $request): JsonResponse
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!$request->filled($field)) {
                return response()->json(['success' => false, 'message' => 'Missing required field: '.$field], 400);
            }
        }

        // The signup form's disclaimer checkbox only gated submission
        // client-side — a direct POST bypassing the browser could register
        // an account with no consent recorded at all. Enforcing it here too.
        if ($request->input('agree_to_disclaimer') !== '1') {
            return response()->json(['success' => false, 'message' => 'You must agree to the Data Privacy Policy and Disclaimer to register.'], 400);
        }

        $documentName = Uploader::storeNamed(
            $this->fileArray($request, 'verification_documents'),
            'documents',
            ['image/jpeg', 'image/png', 'application/pdf']
        );

        if (!$documentName) {
            return response()->json(['success' => false, 'message' => 'Document upload failed.'], 400);
        }

        $email = $request->input('email');
        $secondaryEmail = $request->input('secondary_email');
        $firstName = $request->input('first_name');
        $lastName = $request->input('last_name');

        $userModel = new User();
        $userId = $userModel->create($email, $secondaryEmail, password_hash($request->input('password'), PASSWORD_DEFAULT), 'alumni', 'Pending', true);

        $campusId = (int) $request->input('campus_id');

        try {
            (new Alumni())->register($userId, [
                'first_name' => $firstName,
                'middle_name' => $request->input('middle_name', ''),
                'last_name' => $lastName,
                'birthdate' => $request->input('birthdate'),
                'contact' => $request->input('contact'),
                'gender' => $request->input('gender'),
                'civil_status' => $request->input('civil_status'),
                'city' => $request->input('city'),
                'province' => $request->input('province'),
                'year_graduated' => $request->input('year_graduated'),
                'college' => $request->input('college'),
                'course' => $request->input('course'),
                'campus_id' => $campusId,
            ], $documentName);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        $mail = new MailService();
        $mail->send($email, "{$firstName} {$lastName}", 'Welcome to LSPU EIS Alumni Portal', $this->welcomeEmailBody($firstName));
        if ($secondaryEmail) {
            $mail->send($secondaryEmail, "{$firstName} {$lastName}", 'Welcome to LSPU EIS Alumni Portal', $this->welcomeEmailBody($firstName));
        }

        $notification = new Notification();
        $recipientIds = array_merge($userModel->adminIdsByCampus($campusId), $userModel->allIdsByRole('superadmin'));
        foreach ($recipientIds as $adminId) {
            $notification->create($adminId, 'registration', 'New alumni registration pending approval', "{$firstName} {$lastName} has registered and is awaiting approval.");
        }

        return response()->json(['success' => true, 'message' => 'Registration successful! Please check your email for confirmation.']);
    }

    /** Adapts Laravel's UploadedFile back into the $_FILES-shaped array Uploader expects. */
    private function fileArray(Request $request, string $key): array
    {
        $file = $request->file($key);
        if (!$file) {
            return [];
        }

        return [
            'name' => $file->getClientOriginalName(),
            'type' => $file->getClientMimeType(),
            'tmp_name' => $file->getPathname(),
            'error' => $file->getError(),
            'size' => $file->getSize(),
        ];
    }

    private function welcomeEmailBody(string $firstName): string
    {
        $safeName = htmlspecialchars($firstName);

        return MailService::wrap(
            "Welcome, {$safeName}!",
            '<p>Your alumni account has been created successfully.</p>'
                .'<p>We will verify your documents and notify you once your account is fully activated.</p>',
            'Login to LSPU EIS',
            config('app.url').'/login'
        );
    }
}
