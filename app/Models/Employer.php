<?php

namespace App\Models;

use App\Concerns\LegacyQueries;
use Illuminate\Support\Facades\DB;

/** Ported from backend/Models/Employer.php — see User.php's docblock for the porting approach. */
class Employer
{
    use LegacyQueries;

    public function allOrdered(): array
    {
        return $this->selectAll('SELECT * FROM employer ORDER BY company_name ASC');
    }

    public function contactDetailsByUserId(int $userId): ?array
    {
        return $this->selectOne('SELECT company_name, contact_email, contact_number FROM employer WHERE user_id = ? LIMIT 1', [$userId]);
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->selectOne('SELECT employer_id, user_id, company_name, company_logo, company_location, contact_email,
                   contact_number, industry_type, nature_of_business, tin, date_established, company_type,
                   accreditation_status, document_file
            FROM employer WHERE user_id = ? LIMIT 1', [$userId]);
    }

    public function allByStatus(string $status): array
    {
        return $this->selectAll('SELECT e.user_id, e.company_name, e.company_logo, e.company_location, e.contact_email,
                       e.contact_number, e.industry_type, e.nature_of_business, e.tin, e.date_established,
                       e.company_type, e.accreditation_status, e.document_file, u.email, u.status
                FROM employer e JOIN user u ON e.user_id = u.user_id
                WHERE u.status = ? ORDER BY e.user_id DESC', [$status]);
    }

    /**
     * Paginated + optionally search-filtered variant of allByStatus(), for
     * companies lists too large to ship in one response. $search matches
     * company name, location, contact email, and industry type — the same
     * fields the frontend's client-side search used to filter on.
     */
    public function allByStatusPaginated(string $status, int $limit, int $offset, string $search = ''): array
    {
        [$where, $params] = $this->statusSearchClause($status, $search);
        $sql = 'SELECT e.user_id, e.company_name, e.company_logo, e.company_location, e.contact_email,
                       e.contact_number, e.industry_type, e.nature_of_business, e.tin, e.date_established,
                       e.company_type, e.accreditation_status, e.document_file, u.email, u.status
                FROM employer e JOIN user u ON e.user_id = u.user_id'
                .$where.
                ' ORDER BY e.user_id DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->selectAll($sql, $params);
    }

    public function countByStatus(string $status, string $search = ''): int
    {
        [$where, $params] = $this->statusSearchClause($status, $search);
        $sql = 'SELECT COUNT(*) as total FROM employer e JOIN user u ON e.user_id = u.user_id'.$where;

        return (int) ($this->selectOne($sql, $params)['total'] ?? 0);
    }

    private function statusSearchClause(string $status, string $search): array
    {
        $where = ' WHERE u.status = ?';
        $params = [$status];

        if ($search !== '') {
            $like = '%'.$search.'%';
            $where .= ' AND (e.company_name LIKE ? OR e.company_location LIKE ? OR e.contact_email LIKE ? OR e.industry_type LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        return [$where, $params];
    }

    public function approve(int $userId): bool
    {
        return $this->runUpdate("UPDATE user SET status = 'Active' WHERE user_id = ?", [$userId]) >= 0;
    }

    public function delete(int $userId): bool
    {
        try {
            DB::transaction(function () use ($userId) {
                // onboarding_checklist.employer_id -> employer.user_id is RESTRICT,
                // not CASCADE (deliberately — deleting an onboarding item
                // shouldn't ever cascade into deleting the employer). Clear
                // this employer's checklist explicitly first, or the DELETE
                // FROM employer below hits the constraint and rolls back.
                $this->runDelete('DELETE FROM onboarding_checklist WHERE employer_id = ?', [$userId]);
                $this->runDelete('DELETE FROM employer WHERE user_id = ?', [$userId]);
                $this->runDelete('DELETE FROM user WHERE user_id = ?', [$userId]);
            });

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function updateEmail(int $userId, string $email): void
    {
        $this->runUpdate('UPDATE user SET email = ? WHERE user_id = ?', [$email, $userId]);
    }

    public function update(int $userId, array $fields): bool
    {
        if (empty($fields)) {
            return false;
        }

        // Some employer accounts ended up with a `user` row but no matching
        // `employer` row (e.g. an interrupted registration), which used to
        // make every profile edit silently no-op. Self-heal by creating the
        // row on first edit.
        if (!$this->findByUserId($userId)) {
            $defaults = [
                'company_name' => '', 'company_logo' => '', 'company_location' => '',
                'contact_email' => '', 'contact_number' => '', 'industry_type' => '',
                'nature_of_business' => '', 'tin' => '', 'date_established' => date('Y-m-d'),
                'company_type' => '', 'accreditation_status' => '', 'document_file' => '',
            ];
            try {
                $this->register($userId, array_merge($defaults, $fields));

                return true;
            } catch (\RuntimeException $e) {
                return false;
            }
        }

        $set = implode(',', array_map(fn ($c) => "{$c} = ?", array_keys($fields)));

        return $this->runUpdate("UPDATE employer SET {$set} WHERE user_id = ?", [...array_values($fields), $userId]) >= 0;
    }

    public function register(int $userId, array $data): void
    {
        try {
            $this->insert('INSERT INTO employer (user_id, company_name, company_logo, company_location, contact_email, contact_number, industry_type, nature_of_business, tin, date_established, company_type, accreditation_status, document_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $userId, $data['company_name'], $data['company_logo'], $data['company_location'],
                $data['contact_email'], $data['contact_number'], $data['industry_type'], $data['nature_of_business'],
                $data['tin'], $data['date_established'], $data['company_type'], $data['accreditation_status'], $data['document_file'],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Employer registration failed: '.$e->getMessage());
        }
    }
}
