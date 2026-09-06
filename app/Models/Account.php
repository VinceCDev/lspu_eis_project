<?php

namespace App\Models;

use App\Concerns\LegacyQueries;
use Illuminate\Support\Facades\DB;

/** Ported from backend/Models/Account.php — see User.php's docblock for the porting approach. */
class Account
{
    use LegacyQueries;

    public function setStatus(int $userId, string $role, string $status): bool
    {
        $table = $role === 'admin' ? 'administrator' : 'user';

        return $this->runUpdate("UPDATE {$table} SET status = ? WHERE user_id = ?", [$status, $userId]) >= 0;
    }

    /** Employers aren't campus-scoped and are managed at the superadmin level only. */
    public function allAccounts(?int $campusId = null, bool $includeEmployers = true): array
    {
        $accounts = [];

        $sql = "SELECT u.user_id, u.user_role, u.email, u.last_login, a.first_name, a.middle_name, a.last_name, a.profile_pic, a.position, a.department, a.campus_id, u.status FROM user u INNER JOIN administrator a ON u.user_id = a.user_id WHERE u.user_role IN ('admin', 'superadmin')";
        if ($campusId !== null) {
            $sql .= ' AND a.campus_id = '.((int) $campusId);
        }
        foreach ($this->selectAll($sql) as $row) {
            $accounts[] = [
                'user_id' => $row['user_id'],
                'user_role' => $row['user_role'],
                'email' => $row['email'],
                'last_login' => $row['last_login'] ? date('Y-m-d', strtotime($row['last_login'])) : null,
                'name' => trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']),
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'profile_pic' => $row['profile_pic'],
                'position' => $row['position'],
                'department' => $row['department'],
                'campus_id' => $row['campus_id'],
                'status' => $row['status'],
            ];
        }

        if ($includeEmployers) {
            foreach ($this->selectAll("SELECT u.user_id, u.user_role, u.email, u.last_login, e.company_name, e.company_logo, e.industry_type, u.status FROM user u INNER JOIN employer e ON u.user_id = e.user_id WHERE u.user_role = 'employer'") as $row) {
                $accounts[] = [
                    'user_id' => $row['user_id'],
                    'user_role' => 'employer',
                    'email' => $row['email'],
                    'last_login' => $row['last_login'] ? date('Y-m-d', strtotime($row['last_login'])) : null,
                    'name' => $row['company_name'],
                    'company_name' => $row['company_name'],
                    'industry_type' => $row['industry_type'],
                    'profile_pic' => $row['company_logo'],
                    'position' => $row['industry_type'],
                    'department' => '',
                    'status' => $row['status'],
                ];
            }
        }

        $sql = "SELECT u.user_id, u.user_role, u.email, u.last_login, a.first_name, a.middle_name, a.last_name, a.profile_pic, a.course, a.college, a.campus_id, u.status FROM user u INNER JOIN alumni a ON u.user_id = a.user_id WHERE u.user_role = 'alumni'";
        if ($campusId !== null) {
            $sql .= ' AND a.campus_id = '.((int) $campusId);
        }
        foreach ($this->selectAll($sql) as $row) {
            $accounts[] = [
                'user_id' => $row['user_id'],
                'user_role' => 'alumni',
                'email' => $row['email'],
                'last_login' => $row['last_login'] ? date('Y-m-d', strtotime($row['last_login'])) : null,
                'name' => trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']),
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'profile_pic' => $row['profile_pic'],
                'position' => $row['course'],
                'department' => $row['college'],
                'campus_id' => $row['campus_id'],
                'status' => $row['status'],
            ];
        }

        return $accounts;
    }

    /** Admins + alumni contacts an employer is allowed to message (active accounts only). */
    public function messageableForEmployer(): array
    {
        $accounts = [];

        foreach ($this->selectAll("SELECT u.user_id, u.user_role, u.email, a.first_name, a.middle_name, a.last_name, a.profile_pic, a.position, a.department, u.status FROM user u INNER JOIN administrator a ON u.user_id = a.user_id WHERE u.user_role IN ('admin', 'superadmin') AND u.status = 'active'") as $row) {
            $accounts[] = [
                'user_id' => $row['user_id'],
                'user_role' => $row['user_role'],
                'email' => $row['email'],
                'name' => trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']),
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'profile_pic' => $row['profile_pic'],
                'position' => $row['position'],
                'department' => $row['department'],
                'status' => $row['status'],
            ];
        }

        foreach ($this->selectAll("SELECT u.user_id, u.user_role, u.email, a.first_name, a.middle_name, a.last_name, a.profile_pic, a.course, a.college, u.status FROM user u INNER JOIN alumni a ON u.user_id = a.user_id WHERE u.user_role = 'alumni' AND u.status = 'active'") as $row) {
            $accounts[] = [
                'user_id' => $row['user_id'],
                'user_role' => 'alumni',
                'email' => $row['email'],
                'name' => trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']),
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'profile_pic' => $row['profile_pic'],
                'position' => $row['course'],
                'department' => $row['college'],
                'status' => $row['status'],
            ];
        }

        return $accounts;
    }

    public function createAdmin(int $userId, string $firstName, string $middleName, string $lastName, ?string $profilePic, ?int $campusId = null): void
    {
        // `address` has no DB default and is meant to be filled in later by
        // the admin themselves via their own profile page (see
        // Admin\ProfileController's own updatable-field list, which
        // already includes 'address' as an editable-after-the-fact field)
        // — empty string is this codebase's established placeholder for
        // "not yet provided" here, same convention Employer\ProfileController
        // already uses for company_location.
        $this->insert('INSERT INTO administrator (user_id, first_name, middle_name, last_name, profile_pic, campus_id, address) VALUES (?, ?, ?, ?, ?, ?, ?)', [$userId, $firstName, $middleName, $lastName, $profilePic, $campusId, '']);
    }

    public function adminDetailsByUserId(int $userId, string $email): ?array
    {
        $row = $this->selectOne('SELECT a.first_name, a.middle_name, a.last_name, a.gender, a.contact, a.profile_pic, a.status, a.address, c.name AS campus_name
            FROM administrator a
            LEFT JOIN campus c ON a.campus_id = c.campus_id
            WHERE a.user_id = ? LIMIT 1', [$userId]);

        if (!$row) {
            return null;
        }

        $profilePic = $row['profile_pic'];
        $profilePicUrl = $profilePic
            ? (str_starts_with($profilePic, 'uploads/') ? $profilePic : 'uploads/profile_picture/'.$profilePic)
            : null;

        return [
            'profile_pic' => $profilePicUrl,
            'name' => trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']),
            'first_name' => $row['first_name'],
            'middle_name' => $row['middle_name'],
            'last_name' => $row['last_name'],
            'email' => $email,
            'phone' => $row['contact'],
            'address' => $row['address'] ?: '',
            'campus_name' => $row['campus_name'],
            'gender' => $row['gender'],
            'status' => $row['status'],
        ];
    }

    public function adminCampusId(int $userId): ?int
    {
        $row = $this->selectOne('SELECT campus_id FROM administrator WHERE user_id = ? LIMIT 1', [$userId]);

        return $row && $row['campus_id'] !== null ? (int) $row['campus_id'] : null;
    }

    public function alumniCampusId(int $userId): ?int
    {
        $row = $this->selectOne('SELECT campus_id FROM alumni WHERE user_id = ? LIMIT 1', [$userId]);

        return $row && $row['campus_id'] !== null ? (int) $row['campus_id'] : null;
    }

    public function createEmployer(int $userId, string $companyName, string $industryType, ?string $companyLogo): void
    {
        // The admin quick-create modal only collects company_name/
        // industry_type/logo — every other employer profile column is
        // required at the DB level with no default. Employer\
        // ProfileController's own "no employer row yet" fallback already
        // treats company_location => '' (and the other string fields) as
        // the correct "not yet provided" value, completed later via the
        // employer's own profile page — same convention applied here.
        // date_established has no sensible "unknown" string under this
        // DB's strict SQL mode (DATE columns reject '' outright), so it's
        // nullable (see the accompanying migration) and passed as NULL.
        $this->insert(
            'INSERT INTO employer (user_id, company_name, company_logo, industry_type, company_location, contact_email, contact_number, nature_of_business, tin, date_established, company_type, accreditation_status, document_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $companyName, $companyLogo, $industryType, '', '', '', '', '', null, '', '', '']
        );
    }

    public function createAlumni(int $userId, string $firstName, string $middleName, string $lastName, ?string $profilePic): void
    {
        // Same situation as createEmployer() above: the admin quick-create
        // modal only collects name/photo — every other alumni profile
        // column is required with no default. String fields get '' (fill
        // in later via the alumni's own profile); birthdate/year_graduated
        // have no valid "unknown" string under this DB's strict SQL mode,
        // so they're nullable (see the accompanying migration) and passed
        // as NULL.
        $this->insert(
            'INSERT INTO alumni (user_id, first_name, middle_name, last_name, profile_pic, verification_document, birthdate, contact, gender, civil_status, city, province, year_graduated, college, course) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $firstName, $middleName, $lastName, $profilePic, '', null, '', '', '', '', '', null, '', '']
        );
    }

    public function updateAdmin(int $userId, string $email, string $firstName, string $middleName, string $lastName, string $status, ?string $profilePic, ?int $campusId = null): void
    {
        $this->runUpdate('UPDATE user SET email=? WHERE user_id=?', [$email, $userId]);

        $set = ['first_name=?', 'middle_name=?', 'last_name=?', 'status=?'];
        $params = [$firstName, $middleName, $lastName, $status];
        if ($profilePic) {
            $set[] = 'profile_pic=?';
            $params[] = $profilePic;
        }
        if ($campusId !== null) {
            $set[] = 'campus_id=?';
            $params[] = $campusId;
        }
        $params[] = $userId;

        $this->runUpdate('UPDATE administrator SET '.implode(',', $set).' WHERE user_id=?', $params);
    }

    public function updateEmployer(int $userId, string $companyName, string $industryType, string $status, ?string $companyLogo): void
    {
        // `status` lives on `user`, not `employer` — this table has no such column.
        $this->runUpdate('UPDATE user SET status=? WHERE user_id=?', [$status, $userId]);

        $set = ['company_name=?', 'industry_type=?'];
        $params = [$companyName, $industryType];
        if ($companyLogo) {
            $set[] = 'company_logo=?';
            $params[] = $companyLogo;
        }
        $params[] = $userId;

        $this->runUpdate('UPDATE employer SET '.implode(',', $set).' WHERE user_id=?', $params);
    }

    public function updateAlumni(int $userId, string $email, string $firstName, string $middleName, string $lastName, string $status, ?string $profilePic): void
    {
        // Same as updateEmployer() above — `alumni` has no `status` column, it belongs on `user`.
        $this->runUpdate('UPDATE user SET email=?, status=? WHERE user_id=?', [$email, $status, $userId]);

        $set = ['first_name=?', 'middle_name=?', 'last_name=?'];
        $params = [$firstName, $middleName, $lastName];
        if ($profilePic) {
            $set[] = 'profile_pic=?';
            $params[] = $profilePic;
        }
        $params[] = $userId;

        $this->runUpdate('UPDATE alumni SET '.implode(',', $set).' WHERE user_id=?', $params);
    }

    public function updateOwnAdminProfile(int $userId, array $fields): void
    {
        if (isset($fields['email'])) {
            $this->runUpdate('UPDATE user SET email=? WHERE user_id=?', [$fields['email'], $userId]);
            unset($fields['email']);
        }

        if (empty($fields)) {
            return;
        }

        $set = implode(',', array_map(fn ($c) => "{$c} = ?", array_keys($fields)));
        $this->runUpdate('UPDATE administrator SET '.$set.' WHERE user_id = ?', [...array_values($fields), $userId]);
    }

    public function updateAdminProfilePic(int $userId, string $profilePic): void
    {
        $this->runUpdate('UPDATE administrator SET profile_pic=? WHERE user_id=?', [$profilePic, $userId]);
    }

    public function delete(int $userId, string $role): bool
    {
        $table = match ($role) {
            'admin' => 'administrator',
            'employer' => 'employer',
            'alumni' => 'alumni',
            default => null,
        };

        if (!$table) {
            return false;
        }

        try {
            DB::transaction(function () use ($userId, $table) {
                // saved_jobs.user_id -> user.user_id is RESTRICT, not CASCADE
                // (deliberately). Clear this first or the DELETE FROM user
                // below hits the constraint and rolls back with no clear
                // reason surfaced.
                $this->runDelete('DELETE FROM saved_jobs WHERE user_id = ?', [$userId]);
                $this->runDelete("DELETE FROM {$table} WHERE user_id = ?", [$userId]);
                $this->runDelete('DELETE FROM user WHERE user_id = ?', [$userId]);
            });

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
