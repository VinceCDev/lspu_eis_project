<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/Job.php — see User.php's docblock for the porting approach. */
class Job
{
    use LegacyQueries;

    public function titleById(int $jobId): ?string
    {
        return $this->selectOne('SELECT title FROM jobs WHERE job_id = ? LIMIT 1', [$jobId])['title'] ?? null;
    }

    public function titleByIdForEmployer(int $jobId, int $employerId): ?string
    {
        return $this->selectOne('SELECT title FROM jobs WHERE job_id = ? AND employer_id = ? LIMIT 1', [$jobId, $employerId])['title'] ?? null;
    }

    /** @return array{employer_question: string, employer_question_required: bool}|null */
    public function questionMetaById(int $jobId): ?array
    {
        $row = $this->selectOne('SELECT employer_question, employer_question_required FROM jobs WHERE job_id = ? LIMIT 1', [$jobId]);

        return $row ? ['employer_question' => $row['employer_question'], 'employer_question_required' => (bool) $row['employer_question_required']] : null;
    }

    public function employerIdByJobId(int $jobId): ?int
    {
        $row = $this->selectOne('SELECT employer_id FROM jobs WHERE job_id = ? LIMIT 1', [$jobId]);

        return $row ? (int) $row['employer_id'] : null;
    }

    public function detailsWithCompanyById(int $jobId): ?array
    {
        $job = $this->selectOne('SELECT job_id, employer_id, title, type, work_setup, classification, location, salary, status, created_at, description, requirements, qualifications, employer_question, employer_question_required FROM jobs WHERE job_id = ? LIMIT 1', [$jobId]);

        if (!$job) {
            return null;
        }

        $company = $this->selectOne('SELECT company_logo, company_name, company_location, contact_email, contact_number, nature_of_business, industry_type, accreditation_status FROM employer WHERE user_id = ? LIMIT 1', [$job['employer_id']]);
        if ($company && $company['company_logo']) {
            $company['company_logo'] = basename($company['company_logo']);
        }

        return ['jobDetails' => $job, 'companyDetails' => $company ?: []];
    }

    public function allWithEmployer(): array
    {
        return $this->selectAll('SELECT j.job_id, j.employer_id, j.title, j.location, j.type, j.work_setup, j.classification, j.salary,
                       j.description, j.requirements, j.qualifications, j.status, j.employer_question, j.employer_question_required, j.created_at,
                       e.company_name, e.company_logo
                FROM jobs j
                LEFT JOIN employer e ON j.employer_id = e.user_id
                ORDER BY j.created_at DESC, j.job_id DESC');
    }

    /**
     * Paginated + optionally search-filtered variant of allWithEmployer(),
     * for lists too large to ship in one response. $search matches the same
     * columns (title, company, type, location, status) the frontend's
     * client-side search used to filter on, so switching a page over to
     * this doesn't change what a search term matches — only where the
     * filtering happens.
     */
    public function allWithEmployerPaginated(int $limit, int $offset, string $search = ''): array
    {
        [$where, $params] = $this->searchClause($search);
        $sql = 'SELECT j.job_id, j.employer_id, j.title, j.location, j.type, j.work_setup, j.classification, j.salary,
                       j.description, j.requirements, j.qualifications, j.status, j.employer_question, j.employer_question_required, j.created_at,
                       e.company_name, e.company_logo
                FROM jobs j
                LEFT JOIN employer e ON j.employer_id = e.user_id'
                .$where.
                ' ORDER BY j.created_at DESC, j.job_id DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->selectAll($sql, $params);
    }

    public function countWithEmployer(string $search = ''): int
    {
        [$where, $params] = $this->searchClause($search);
        $sql = 'SELECT COUNT(*) as total FROM jobs j LEFT JOIN employer e ON j.employer_id = e.user_id'.$where;

        return (int) ($this->selectOne($sql, $params)['total'] ?? 0);
    }

    private function searchClause(string $search): array
    {
        if ($search === '') {
            return ['', []];
        }

        $like = '%'.$search.'%';

        return [
            ' WHERE (j.title LIKE ? OR e.company_name LIKE ? OR j.type LIKE ? OR j.location LIKE ? OR j.status LIKE ?)',
            [$like, $like, $like, $like, $like],
        ];
    }

    /** Same as allWithEmployer(), but only postings still open. */
    public function allActiveWithEmployer(): array
    {
        return $this->selectAll('SELECT j.job_id, j.employer_id, j.title, j.location, j.type, j.work_setup, j.classification, j.salary,
                       j.description, j.requirements, j.qualifications, j.status, j.employer_question, j.employer_question_required, j.created_at,
                       e.company_name, e.company_logo
                FROM jobs j
                LEFT JOIN employer e ON j.employer_id = e.user_id
                WHERE j.status = "Active"
                ORDER BY j.created_at DESC, j.job_id DESC');
    }

    /** Auto-closes postings older than 30 days without touching application/interview history. */
    public function closeExpired(int $maxAgeDays = 30): int
    {
        return $this->runUpdate("UPDATE jobs SET status = 'Closed' WHERE status = 'Active' AND created_at <= (NOW() - INTERVAL ? DAY)", [$maxAgeDays]);
    }

    public function whereEmployer(int $employerId): array
    {
        return $this->selectAll('SELECT job_id, employer_id, title, location, type, work_setup, classification, salary,
                       description, requirements, qualifications, status, employer_question, employer_question_required, created_at
                FROM jobs WHERE employer_id = ? ORDER BY created_at DESC, job_id DESC', [$employerId]);
    }

    public function create(array $data): int
    {
        date_default_timezone_set('Asia/Manila');
        $createdAt = date('Y-m-d H:i:s');

        try {
            return $this->insertGetId('INSERT INTO jobs (employer_id, title, type, work_setup, classification, location, salary, status, created_at, description, requirements, qualifications, employer_question, employer_question_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $data['employer_id'], $data['title'], $data['type'], $data['work_setup'], $data['classification'],
                $data['location'], $data['salary'], $data['status'], $createdAt, $data['description'],
                $data['requirements'], $data['qualifications'], $data['employer_question'], $data['employer_question_required'],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Insert failed: '.$e->getMessage());
        }
    }

    public function update(int $jobId, array $data): void
    {
        try {
            $this->runUpdate('UPDATE jobs SET employer_id=?, title=?, type=?, work_setup=?, classification=?, location=?, salary=?, status=?, description=?, requirements=?, qualifications=?, employer_question=?, employer_question_required=? WHERE job_id=?', [
                $data['employer_id'], $data['title'], $data['type'], $data['work_setup'], $data['classification'],
                $data['location'], $data['salary'], $data['status'], $data['description'], $data['requirements'],
                $data['qualifications'], $data['employer_question'], $data['employer_question_required'], $jobId,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Update failed: '.$e->getMessage());
        }
    }

    public function delete(int $jobId): bool
    {
        return $this->runDelete('DELETE FROM jobs WHERE job_id=?', [$jobId]) >= 0;
    }

    public function createForEmployer(int $employerId, array $data): int
    {
        $data['employer_id'] = $employerId;

        return $this->create($data);
    }

    public function updateForEmployer(int $jobId, int $employerId, array $data): bool
    {
        return $this->runUpdate('UPDATE jobs SET title=?, type=?, work_setup=?, classification=?, location=?, salary=?, status=?, description=?, requirements=?, qualifications=?, employer_question=?, employer_question_required=? WHERE job_id=? AND employer_id=?', [
            $data['title'], $data['type'], $data['work_setup'], $data['classification'], $data['location'],
            $data['salary'], $data['status'], $data['description'], $data['requirements'], $data['qualifications'],
            $data['employer_question'], $data['employer_question_required'], $jobId, $employerId,
        ]) > 0;
    }

    public function deleteForEmployer(int $jobId, int $employerId): bool
    {
        return $this->runDelete('DELETE FROM jobs WHERE job_id=? AND employer_id=?', [$jobId, $employerId]) > 0;
    }
}
