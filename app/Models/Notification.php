<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/Notification.php — see User.php's docblock for the porting approach. */
class Notification
{
    use LegacyQueries;

    public function create(int $userId, string $type, string $message, string $details = '', ?int $jobId = null, ?int $messageId = null): void
    {
        if (!$this->isEnabled($userId, $type)) {
            return;
        }

        $this->insert('INSERT INTO notifications (user_id, type, message, details, job_id, message_id) VALUES (?, ?, ?, ?, ?, ?)', [$userId, $type, $message, $details, $jobId, $messageId]);
    }

    /** Missing row means the type has never been toggled off, so it defaults to enabled. */
    public function isEnabled(int $userId, string $type): bool
    {
        $row = $this->selectOne('SELECT enabled FROM notification_preferences WHERE user_id = ? AND type = ?', [$userId, $type]);

        return $row ? (bool) $row['enabled'] : true;
    }

    public function setPreference(int $userId, string $type, bool $enabled): void
    {
        $enabledInt = $enabled ? 1 : 0;
        $this->insert('INSERT INTO notification_preferences (user_id, type, enabled) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE enabled = ?', [$userId, $type, $enabledInt, $enabledInt]);
    }

    /** @param string[] $types @return array<string,bool> */
    public function preferencesForUser(int $userId, array $types): array
    {
        $prefs = [];
        foreach ($types as $type) {
            $prefs[$type] = $this->isEnabled($userId, $type);
        }

        return $prefs;
    }

    public function allForUser(int $userId): array
    {
        $rows = $this->selectAll('SELECT id, type, message, details, is_read, created_at, job_id, message_id FROM notifications WHERE user_id = ? ORDER BY created_at DESC', [$userId]);

        return array_map(static fn ($row) => [
            'id' => $row['id'],
            'type' => $row['type'],
            'message' => $row['message'],
            'details' => $row['details'],
            'read' => (bool) $row['is_read'],
            'time' => $row['created_at'],
            'job_id' => $row['job_id'],
            'message_id' => $row['message_id'],
        ], $rows);
    }

    public function unreadCountForUser(int $userId): int
    {
        return (int) ($this->selectOne('SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0', [$userId])['c'] ?? 0);
    }

    public function markAllRead(int $userId): bool
    {
        return $this->runUpdate('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]) >= 0;
    }

    public function markOneRead(int $notificationId, int $userId): bool
    {
        return $this->runUpdate('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$notificationId, $userId]) > 0;
    }
}
