<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/ReminderSettings.php — see User.php's docblock for the porting approach. */
class ReminderSettings
{
    use LegacyQueries;

    private const DEFAULTS = [
        'business_hours_start' => '9',
        'business_hours_end' => '18',
        'timezone' => 'Asia/Manila',
        'frequency_minutes' => '1',
        'max_reminders_per_day' => '3',
        'email_enabled' => '1',
        'sms_enabled' => '1',
        'email_subject' => 'LSPU EIS - Automated Reminder',
        'email_message' => 'Hello! This is your automated reminder from LSPU Employment and Information System. Please check your account for any updates, job opportunities, or important notifications. Stay connected with your alma mater!',
        'sms_message' => 'LSPU EIS Reminder: Check your account for updates and job opportunities. Stay connected with your alma mater!',
    ];

    public function all(): array
    {
        $settings = self::DEFAULTS;

        foreach ($this->selectAll('SELECT setting_key, setting_value FROM reminder_settings') as $row) {
            if (isset($settings[$row['setting_key']])) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        return $settings;
    }

    public function save(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->insert('INSERT INTO reminder_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?', [$key, $value, $value]);
        }
    }

    public function recentStatistics(int $limit = 7): array
    {
        return $this->selectAll("SELECT * FROM reminder_statistics ORDER BY date DESC LIMIT {$limit}");
    }

    public function recentLogs(int $limit = 20): array
    {
        return $this->selectAll("SELECT * FROM reminder_logs ORDER BY sent_at DESC LIMIT {$limit}");
    }

    public function shouldSendNow(array $settings): bool
    {
        $timezone = new \DateTimeZone($settings['timezone']);
        $now = new \DateTime('now', $timezone);
        $currentHour = (int) $now->format('H');

        if ($currentHour < (int) $settings['business_hours_start'] || $currentHour >= (int) $settings['business_hours_end']) {
            return false;
        }

        $currentMinute = (int) $now->format('i');
        $sendEvery = max(1, (int) $settings['frequency_minutes']);

        return ($currentMinute % $sendEvery) === 0;
    }

    public function withinDailyLimit(int $userId, int $maxPerDay): bool
    {
        $startOfDay = date('Y-m-d 00:00:00');
        $startOfNextDay = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $count = (int) ($this->selectOne(
            "SELECT COUNT(*) as count FROM reminder_logs WHERE recipient = ? AND status = 'sent' AND sent_at >= ? AND sent_at < ?",
            [(string) $userId, $startOfDay, $startOfNextDay]
        )['count'] ?? 0);

        return $count < $maxPerDay;
    }

    public function logReminder(string $type, string $recipient, string $subject, string $message, string $status, ?string $errorMessage = null): void
    {
        $this->insert('INSERT INTO reminder_logs (type, recipient, subject, message, status, error_message, sent_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', [$type, $recipient, $subject, $message, $status, $errorMessage]);
    }

    public function saveStatistics(string $date, array $stats): void
    {
        $this->insert('
            INSERT INTO reminder_statistics
            (date, total_users, emails_sent, emails_failed, sms_sent, sms_failed, total_sent, total_failed)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            total_users = VALUES(total_users),
            emails_sent = VALUES(emails_sent),
            emails_failed = VALUES(emails_failed),
            sms_sent = VALUES(sms_sent),
            sms_failed = VALUES(sms_failed),
            total_sent = VALUES(total_sent),
            total_failed = VALUES(total_failed),
            updated_at = CURRENT_TIMESTAMP
        ', [
            $date, $stats['total_users'], $stats['emails_sent'], $stats['emails_failed'],
            $stats['sms_sent'], $stats['sms_failed'], $stats['total_sent'], $stats['total_failed'],
        ]);
    }
}
