<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;

/**
 * Ported near-verbatim from the original app's backend/Core/Auth.php.
 * Same static-style API and same session keys/shape, so every call site
 * carried over from the old controllers needs minimal changes — only the
 * underlying storage moved from raw $_SESSION to Laravel's Session facade.
 */
class Auth
{
    // Force logout after this many seconds of inactivity — same server-side
    // safety net as the original app (some browsers restore session cookies
    // across restarts, defeating the cookie's own close-the-browser expiry).
    private const IDLE_TIMEOUT_SECONDS = 1800; // 30 minutes

    public static function check(): bool
    {
        return Session::has('email') && Session::has('user_role');
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'user_id' => Session::get('user_id'),
            'email' => Session::get('email'),
            'role' => Session::get('user_role'),
        ];
    }

    public static function role(): ?string
    {
        return Session::get('user_role');
    }

    public static function campusId(): ?int
    {
        $campusId = Session::get('campus_id');

        return $campusId !== null ? (int) $campusId : null;
    }

    public static function login(array $userRow): void
    {
        Session::regenerate(true);

        Session::put('user_id', $userRow['user_id']);
        Session::put('email', $userRow['email']);
        Session::put('user_role', $userRow['user_role']);
        Session::put('campus_id', $userRow['campus_id'] ?? null);
        Session::put('loggedin', true);
        Session::put('last_activity', time());
    }

    public static function logout(): void
    {
        Session::flush();
        Session::invalidate();
    }

    /**
     * Call once per request (registered as global middleware). Logs the
     * user out if they've been idle past IDLE_TIMEOUT_SECONDS; otherwise
     * refreshes the activity timestamp.
     *
     * @return bool true if the session was just expired by this call
     */
    public static function enforceTimeout(): bool
    {
        if (!self::check()) {
            return false;
        }

        $lastActivity = Session::get('last_activity');

        if ($lastActivity !== null && (time() - $lastActivity) > self::IDLE_TIMEOUT_SECONDS) {
            self::logout();

            return true;
        }

        Session::put('last_activity', time());

        return false;
    }

    /**
     * Campus-scoping guard for admin-role actions on campus-owned resources
     * (alumni, applications, ...). Superadmin always passes. A campus-scoped
     * admin must match the resource's campus_id exactly; a resource with no
     * campus_id set belongs to no campus and is forbidden to non-superadmins.
     * Mirrors backend/Core/Auth.php::assertSameCampus() from the original app.
     *
     * @return bool true if allowed
     */
    public static function sameCampus(?int $resourceCampusId): bool
    {
        if (self::role() === 'superadmin') {
            return true;
        }

        return (int) ($resourceCampusId ?? 0) === self::campusId();
    }
}
