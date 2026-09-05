<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/**
 * Ported from backend/Models/User.php in the original app. Same public
 * method names/signatures, bodies rewritten from mysqli to the DB facade
 * (see App\Concerns\LegacyQueries) — SQL text unchanged.
 */
class User
{
    use LegacyQueries;

    public function findByEmail(string $email): ?array
    {
        return $this->selectOne('SELECT u.user_id, u.email, u.secondary_email, u.password, u.user_role, u.status, u.two_factor_enabled, u.two_factor_method, u.last_login, ad.campus_id
            FROM user u
            LEFT JOIN administrator ad ON u.user_id = ad.user_id
            WHERE u.email = ? LIMIT 1', [$email]);
    }

    public function findEmailById(int $userId): ?string
    {
        return $this->selectOne('SELECT email FROM user WHERE user_id = ? LIMIT 1', [$userId])['email'] ?? null;
    }

    public function passwordHashById(int $userId): ?string
    {
        return $this->selectOne('SELECT password FROM user WHERE user_id = ? LIMIT 1', [$userId])['password'] ?? null;
    }

    public function emailExists(string $email): bool
    {
        return $this->selectOne('SELECT user_id FROM user WHERE email = ? LIMIT 1', [$email]) !== null;
    }

    /**
     * $consentGiven should only be true for a self-service signup where the
     * caller has already verified the data-privacy-disclaimer checkbox was
     * actually submitted as accepted — NOT for admin-provisioned accounts.
     * Recording a consent timestamp on those would be a fabricated record,
     * which is worse than recording none at all.
     */
    public function create(string $email, ?string $secondaryEmail, string $hashedPassword, string $role, string $status = 'Pending', bool $consentGiven = false): int
    {
        return $this->insertGetId(
            'INSERT INTO user (email, secondary_email, password, user_role, status, consent_given_at) VALUES (?, ?, ?, ?, ?, '.($consentGiven ? 'NOW()' : 'NULL').')',
            [$email, $secondaryEmail, $hashedPassword, $role, $status]
        );
    }

    public function updateLastLogin(int $userId): void
    {
        $this->runUpdate('UPDATE user SET last_login = NOW() WHERE user_id = ?', [$userId]);
    }

    public function setTwoFactorCode(int $userId, string $code, string $expires): void
    {
        $this->runUpdate('UPDATE user SET two_factor_code = ?, two_factor_expires = ? WHERE user_id = ?', [$code, $expires, $userId]);
    }

    public function getTwoFactorCode(int $userId): ?array
    {
        return $this->selectOne('SELECT two_factor_code, two_factor_expires FROM user WHERE user_id = ?', [$userId]);
    }

    public function clearTwoFactorCode(int $userId): void
    {
        $this->runUpdate('UPDATE user SET two_factor_code = NULL, two_factor_expires = NULL WHERE user_id = ?', [$userId]);
    }

    public function setResetToken(int $userId, string $token, string $expiry): void
    {
        $this->runUpdate('UPDATE user SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?', [$token, $expiry, $userId]);
    }

    public function findByEmailOrSecondary(string $email): ?array
    {
        return $this->selectOne('SELECT user_id, email, secondary_email FROM user WHERE email = ? OR secondary_email = ? LIMIT 1', [$email, $email]);
    }

    public function findByResetToken(string $email, string $token): ?array
    {
        return $this->selectOne('SELECT user_id, reset_token, reset_token_expiry FROM user WHERE (email = ? OR secondary_email = ?) AND reset_token = ? LIMIT 1', [$email, $email, $token]);
    }

    public function allIdsByRole(string $role): array
    {
        $rows = $this->selectAll('SELECT user_id FROM user WHERE user_role = ? AND status = ?', [$role, 'Active']);

        return array_map(static fn ($row) => (int) $row['user_id'], $rows);
    }

    /** Admin user_ids scoped to a campus, for notifying the right campus admin(s). */
    public function adminIdsByCampus(int $campusId): array
    {
        $rows = $this->selectAll("SELECT u.user_id FROM user u
            INNER JOIN administrator ad ON u.user_id = ad.user_id
            WHERE u.user_role = 'admin' AND u.status = 'Active' AND ad.campus_id = ?", [$campusId]);

        return array_map(static fn ($row) => (int) $row['user_id'], $rows);
    }

    public function createdAtById(int $userId): ?string
    {
        return $this->selectOne('SELECT created_at FROM user WHERE user_id = ? LIMIT 1', [$userId])['created_at'] ?? null;
    }

    /** Active alumni with a usable email/contact, for the reminder cron job. */
    public function activeAlumniForReminders(): array
    {
        return $this->selectAll("SELECT
                u.user_id,
                u.email,
                a.contact as phone_number,
                a.course,
                a.college,
                CONCAT(a.first_name, ' ', COALESCE(a.middle_name, ''), ' ', a.last_name) as full_name
            FROM user u
            INNER JOIN alumni a ON u.user_id = a.user_id
            WHERE u.status = 'Active'
            AND u.user_role = 'alumni'
            AND u.email IS NOT NULL AND u.email != ''
            AND a.contact IS NOT NULL AND a.contact != ''
            ORDER BY u.user_id");
    }

    public function markTutorialCompleted(int $userId): bool
    {
        return $this->runUpdate('UPDATE user SET tutorial_completed = 1, tutorial_completed_date = NOW() WHERE user_id = ?', [$userId]) > 0;
    }

    public function updatePassword(int $userId, string $hashedPassword): void
    {
        $this->runUpdate('UPDATE user SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?', [$hashedPassword, $userId]);
    }

    public function twoFactorEnabled(int $userId): bool
    {
        $row = $this->selectOne('SELECT two_factor_enabled FROM user WHERE user_id = ? LIMIT 1', [$userId]);

        return $row !== null && (bool) $row['two_factor_enabled'];
    }

    public function setTwoFactorEnabled(int $userId, bool $enabled): void
    {
        $this->runUpdate('UPDATE user SET two_factor_enabled = ? WHERE user_id = ?', [$enabled ? 1 : 0, $userId]);
    }
}
