<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\ReminderSettings;
use App\Models\User;
use App\Services\MailService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint for an external cron service to trigger alumni reminder emails/SMS.
 * Protected by a shared secret (CRON_SECRET in .env). Ported from
 * backend/Controllers/Shared/ReminderCronController.php.
 */
class ReminderCronController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        date_default_timezone_set('Asia/Manila');

        $secret = env('CRON_SECRET', '');
        $provided = $request->query('key', '');

        if ($secret === '' || !hash_equals($secret, (string) $provided)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Runs on every cron ping, independent of the reminder system's own
        // business-hours/frequency gating below.
        $closedCount = (new Job())->closeExpired(30);

        $settingsModel = new ReminderSettings();
        $settings = $settingsModel->all();

        if (!$settingsModel->shouldSendNow($settings)) {
            return response()->json([
                'success' => true,
                'message' => 'Not time to send reminders',
                'data' => [
                    'current_time' => date('Y-m-d H:i:s'),
                    'jobs_closed' => $closedCount,
                    'business_hours' => $settings['business_hours_start'].':00 - '.$settings['business_hours_end'].':00',
                    'frequency' => 'Every '.$settings['frequency_minutes'].' minutes',
                ],
            ]);
        }

        $recipients = (new User())->activeAlumniForReminders();
        $mail = new MailService();
        $sms = new SmsService();

        $emailSuccess = 0;
        $emailFailed = 0;
        $smsSuccess = 0;
        $smsFailed = 0;
        $processed = [];

        foreach ($recipients as $recipient) {
            if (!$settingsModel->withinDailyLimit((int) $recipient['user_id'], (int) $settings['max_reminders_per_day'])) {
                $processed[] = ['user_id' => $recipient['user_id'], 'name' => $recipient['full_name'], 'status' => 'skipped', 'reason' => 'Daily limit reached'];
                continue;
            }

            $result = ['user_id' => $recipient['user_id'], 'name' => $recipient['full_name'], 'email' => [], 'sms' => []];

            if (!empty($recipient['email']) && $settings['email_enabled'] === '1') {
                $sent = $mail->send(
                    $recipient['email'],
                    $recipient['full_name'],
                    $settings['email_subject'],
                    $this->emailBody($recipient, $settings['email_message'])
                );

                if ($sent) {
                    ++$emailSuccess;
                    $settingsModel->logReminder('email', $recipient['email'], $settings['email_subject'], $settings['email_message'], 'sent');
                    $result['email'] = ['status' => 'sent'];
                } else {
                    ++$emailFailed;
                    $settingsModel->logReminder('email', $recipient['email'], $settings['email_subject'], $settings['email_message'], 'failed', 'Mail send failed');
                    $result['email'] = ['status' => 'failed'];
                }
            }

            if (!empty($recipient['phone_number']) && $settings['sms_enabled'] === '1') {
                $smsResult = $sms->send($recipient['phone_number'], $settings['sms_message']);

                if ($smsResult['success']) {
                    ++$smsSuccess;
                    $settingsModel->logReminder('sms', $recipient['phone_number'], 'SMS Reminder', $settings['sms_message'], 'sent');
                    $result['sms'] = ['status' => 'sent'];
                } else {
                    ++$smsFailed;
                    $settingsModel->logReminder('sms', $recipient['phone_number'], 'SMS Reminder', $settings['sms_message'], 'failed', $smsResult['message']);
                    $result['sms'] = ['status' => 'failed', 'message' => $smsResult['message']];
                }
            }

            $processed[] = $result;
        }

        $stats = [
            'total_users' => count($recipients),
            'emails_sent' => $emailSuccess,
            'emails_failed' => $emailFailed,
            'sms_sent' => $smsSuccess,
            'sms_failed' => $smsFailed,
            'total_sent' => $emailSuccess + $smsSuccess,
            'total_failed' => $emailFailed + $smsFailed,
            'jobs_closed' => $closedCount,
        ];
        $settingsModel->saveStatistics(date('Y-m-d'), $stats);

        return response()->json([
            'success' => true,
            'message' => 'Reminder system executed successfully',
            'data' => [
                'summary' => $stats,
                'processed_users' => $processed,
                'execution_time' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    private function emailBody(array $recipient, string $message): string
    {
        $name = htmlspecialchars($recipient['full_name']);
        $extra = '';

        if (!empty($recipient['course']) && !empty($recipient['college'])) {
            $extra = '<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
                .'<p style="margin:0 0 6px;"><strong>Your Academic Details</strong></p>'
                .'<p style="margin:0 0 4px;"><strong>Course:</strong> '.htmlspecialchars($recipient['course']).'</p>'
                .'<p style="margin:0;"><strong>College:</strong> '.htmlspecialchars($recipient['college']).'</p>'
                .'</div>';
        }

        return MailService::wrap(
            "Hello {$name}!",
            "<p>{$message}</p>".$extra,
            'Access Your Account',
            config('app.url')
        );
    }
}
