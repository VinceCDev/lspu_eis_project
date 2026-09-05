<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/**
 * Generic sliding-window attempt counter, keyed by an arbitrary bucket +
 * identifier (IP, email, user_id, ...). Ported from backend/Models/RateLimiter.php.
 */
class RateLimiter
{
    use LegacyQueries;

    public function tooManyAttempts(string $bucket, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        $since = date('Y-m-d H:i:s', time() - $windowSeconds);
        $count = (int) ($this->selectOne('SELECT COUNT(*) as c FROM rate_limit_attempts WHERE bucket = ? AND identifier = ? AND created_at >= ?', [$bucket, $identifier, $since])['c'] ?? 0);

        return $count >= $maxAttempts;
    }

    public function hit(string $bucket, string $identifier): void
    {
        $this->insert('INSERT INTO rate_limit_attempts (bucket, identifier, created_at) VALUES (?, ?, NOW())', [$bucket, $identifier]);
    }

    public function clear(string $bucket, string $identifier): void
    {
        $this->runDelete('DELETE FROM rate_limit_attempts WHERE bucket = ? AND identifier = ?', [$bucket, $identifier]);
    }
}
