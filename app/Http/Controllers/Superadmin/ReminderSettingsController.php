<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ReminderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Superadmin/ReminderSettingsController.php. */
class ReminderSettingsController extends Controller
{
    public function index()
    {
        $model = new ReminderSettings();

        return view('superadmin.reminder_settings', [
            'title' => 'Reminder Settings | LSPU - EIS',
            'active' => 'superadmin_reminder_settings',
            'pageJs' => 'superadmin_reminder_settings.js',
            'recentStats' => $model->recentStatistics(),
            'recentLogs' => $model->recentLogs(),
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['success' => true, 'settings' => (new ReminderSettings())->all()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $settings = [
            'business_hours_start' => $request->input('business_hours_start', '9'),
            'business_hours_end' => $request->input('business_hours_end', '18'),
            'timezone' => $request->input('timezone', 'Asia/Manila'),
            'frequency_minutes' => $request->input('frequency_minutes', '1'),
            'max_reminders_per_day' => $request->input('max_reminders_per_day', '3'),
            'email_enabled' => $request->boolean('email_enabled') ? '1' : '0',
            'sms_enabled' => $request->boolean('sms_enabled') ? '1' : '0',
            'email_subject' => $request->input('email_subject', ''),
            'email_message' => $request->input('email_message', ''),
            'sms_message' => $request->input('sms_message', ''),
        ];

        (new ReminderSettings())->save($settings);

        return response()->json(['success' => true, 'message' => 'Reminder settings updated successfully']);
    }
}
