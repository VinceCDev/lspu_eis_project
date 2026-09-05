<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\ReminderSettings;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Admin/SettingsController.php. */
class SettingsController extends Controller
{
    /** Types this role actually receives, curated from the Notification::create() call-site audit. */
    private const NOTIFICATION_TYPES = [
        'registration' => 'New registrations',
        'system' => 'System announcements',
    ];

    public function index()
    {
        $userId = (int) Auth::user()['user_id'];
        $isSuperadmin = Auth::role() === 'superadmin';
        $siteSettingModel = new SiteSetting();

        $data = [
            'title' => 'Settings | LSPU - EIS',
            'active' => Auth::role() === 'superadmin' ? 'superadmin_settings' : 'admin_settings',
            'pageJs' => 'admin_settings.js',
            'notificationTypes' => self::NOTIFICATION_TYPES,
            'preferences' => (new Notification())->preferencesForUser($userId, array_keys(self::NOTIFICATION_TYPES)),
            'twoFactorEnabled' => (new User())->twoFactorEnabled($userId),
            'isSuperadmin' => $isSuperadmin,
            'passwordPolicy' => $siteSettingModel->passwordPolicy(),
        ];

        if ($isSuperadmin) {
            $reminderModel = new ReminderSettings();
            $data['recentStats'] = $reminderModel->recentStatistics();
            $data['recentLogs'] = $reminderModel->recentLogs();
            $data['heroSettings'] = $siteSettingModel->all();
        }

        return view('admin.settings', $data);
    }

    /** Superadmin-only: configure the password policy every admin/superadmin password change is validated against. */
    public function updatePasswordPolicy(Request $request): JsonResponse
    {
        $minLength = max(8, min(64, (int) $request->input('password_min_length', 10)));

        $settingsModel = new SiteSetting();
        $settingsModel->save([
            'password_min_length' => (string) $minLength,
            'password_require_uppercase' => $request->boolean('password_require_uppercase') ? '1' : '0',
            'password_require_number' => $request->boolean('password_require_number') ? '1' : '0',
            'password_require_symbol' => $request->boolean('password_require_symbol') ? '1' : '0',
        ]);

        $userId = (int) Auth::user()['user_id'];
        (new AuditLog())->log($userId, Auth::user()['email'] ?? null, Auth::role(), 'update_password_policy', 'site_settings', null, 'Updated the admin/superadmin password requirements.');

        return response()->json(['success' => true, 'message' => 'Password requirements updated.']);
    }

    private function passwordRequirementsText(array $policy): string
    {
        $parts = ["at least {$policy['min_length']} characters"];
        if ($policy['require_uppercase']) {
            $parts[] = 'an uppercase letter';
        }
        if ($policy['require_number']) {
            $parts[] = 'a number';
        }
        if ($policy['require_symbol']) {
            $parts[] = 'a symbol';
        }

        $last = array_pop($parts);

        return 'Password must be '.($parts ? implode(', ', $parts).', and '.$last : $last).'.';
    }

    public function toggleTwoFactor(Request $request): JsonResponse
    {
        $enabled = in_array($request->input('enabled'), [1, '1', true, 'true'], true);
        $userId = (int) Auth::user()['user_id'];

        (new User())->setTwoFactorEnabled($userId, $enabled);
        (new AuditLog())->log(
            $userId,
            Auth::user()['email'] ?? null,
            Auth::role(),
            'toggle_2fa',
            'user',
            $userId,
            $enabled ? 'Enabled two-factor authentication.' : 'Disabled two-factor authentication.'
        );

        return response()->json([
            'success' => true,
            'message' => $enabled ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.',
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

        $policy = (new SiteSetting())->passwordPolicy();
        $violatesPolicy = strlen($new) < $policy['min_length']
            || ($policy['require_uppercase'] && !preg_match('/[A-Z]/', $new))
            || ($policy['require_number'] && !preg_match('/[0-9]/', $new))
            || ($policy['require_symbol'] && !preg_match('/[^A-Za-z0-9]/', $new));

        if ($violatesPolicy) {
            return response()->json(['success' => false, 'message' => $this->passwordRequirementsText($policy)]);
        }

        $userId = (int) Auth::user()['user_id'];
        $userModel = new User();
        $hash = $userModel->passwordHashById($userId);

        if (!$hash || !password_verify($current, $hash)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.']);
        }

        $userModel->updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
        (new AuditLog())->log($userId, Auth::user()['email'] ?? null, Auth::role(), 'change_password', 'user', $userId, 'Changed own account password.');

        return response()->json(['success' => true, 'message' => 'Password updated successfully.']);
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
