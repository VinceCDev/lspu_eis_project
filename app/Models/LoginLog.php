<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/LoginLog.php — see User.php's docblock for the porting approach. */
class LoginLog
{
    use LegacyQueries;

    public function log(string $email, string $status, ?int $userId = null, ?string $failureReason = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $attemptTime = date('Y-m-d H:i:s');

        $this->insert('INSERT INTO login_logs (user_id, email, ip_address, user_agent, attempt_time, status, failure_reason)
                             VALUES (?, ?, ?, ?, ?, ?, ?)', [$userId, $email, $ip, $userAgent, $attemptTime, $status, $failureReason]);
    }

    public function isBruteForced(string $email): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $timeLimit = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        $ipAttempts = (int) ($this->selectOne("SELECT COUNT(*) as attempts FROM login_logs WHERE ip_address = ? AND attempt_time >= ? AND status = 'failed'", [$ip, $timeLimit])['attempts'] ?? 0);
        $emailAttempts = (int) ($this->selectOne("SELECT COUNT(*) as attempts FROM login_logs WHERE email = ? AND attempt_time >= ? AND status = 'failed'", [$email, $timeLimit])['attempts'] ?? 0);

        return $ipAttempts >= 10 || $emailAttempts >= 5;
    }
}
