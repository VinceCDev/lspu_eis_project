<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Employer/SettingsController.php. */
class SettingsController extends Controller
{
    /** Types this role actually receives, curated from the Notification::create() call-site audit. */
    private const NOTIFICATION_TYPES = [
        'new_application' => 'New applicants',
        'hired' => 'Hire confirmations',
        'system' => 'System announcements',
    ];

    public function index()
    {
        $userId = (int) Auth::user()['user_id'];

        return view('employer.settings', [
            'title' => 'Settings | LSPU - EIS',
            'active' => 'employer_settings',
            'pageJs' => 'employer_settings.js',
            'notificationTypes' => self::NOTIFICATION_TYPES,
            'preferences' => (new Notification())->preferencesForUser($userId, array_keys(self::NOTIFICATION_TYPES)),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('confirm_password', '');

        if ($current === '' || $new === '' || $confirm === '') {
            return response()->json(['success' => false, 'message' => 'All fields are required.']);
        }

        if ($new !== $confirm) {
            return response()->json(['success' => false, 'message' => 'New password and confirmation do not match.']);
        }

        if (strlen($new) < 8) {
            return response()->json(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        }

        $userId = (int) Auth::user()['user_id'];
        $userModel = new User();
        $hash = $userModel->passwordHashById($userId);

        if (!$hash || !password_verify($current, $hash)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.']);
        }

        $userModel->updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
        (new AuditLog())->log($userId, Auth::user()['email'] ?? null, 'employer', 'change_password', 'user', $userId, 'Changed own account password.');

        return response()->json(['success' => true, 'message' => 'Password updated successfully.']);
    }

    /**
     * RA 10173 right-to-erasure. Requires the current password as
     * confirmation, since this is irreversible.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $password = (string) $request->input('password', '');

        if ($password === '') {
            return response()->json(['success' => false, 'message' => 'Please enter your password to confirm account deletion.']);
        }

        $userId = (int) Auth::user()['user_id'];
        $userModel = new User();
        $hash = $userModel->passwordHashById($userId);

        if (!$hash || !password_verify($password, $hash)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password.']);
        }

        $email = Auth::user()['email'] ?? null;
        $ok = (new Account())->delete($userId, 'employer');
        if (!$ok) {
            return response()->json(['success' => false, 'message' => 'Failed to delete account. Please try again or contact support.']);
        }

        (new AuditLog())->log($userId, $email, 'employer', 'delete_account', 'employer', $userId, 'Self-service account deletion.');
        Auth::logout();

        return response()->json(['success' => true, 'message' => 'Your account has been permanently deleted.']);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $input = $request->all();
        $userId = (int) Auth::user()['user_id'];
        $notificationModel = new Notification();

        foreach (array_keys(self::NOTIFICATION_TYPES) as $type) {
            if (array_key_exists($type, $input)) {
                $enabled = in_array($input[$type], [1, '1', true, 'true'], true);
                $notificationModel->setPreference($userId, $type, $enabled);
            }
        }

        return response()->json(['success' => true, 'message' => 'Notification preferences updated.']);
    }
}
