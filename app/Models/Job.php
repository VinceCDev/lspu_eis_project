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

    /**
     * A job can now carry any number of employer questions (job_questions
     * table) instead of the single employer_question column this used to
     * read — kept for any caller still expecting the single-question shape,
     * but now backed by question #1 (sort_order 0) if one exists.
     *
     * @return array{employer_question: string, employer_question_required: bool}|null
     */
    public function questionMetaById(int $jobId): ?array
    {
        $row = $this->selectOne('SELECT question_text, is_required FROM job_questions WHERE job_id = ? ORDER BY sort_order ASC LIMIT 1', [$jobId]);

        return $row ? ['employer_question' => $row['question_text'], 'employer_question_required' => (bool) $row['is_required']] : null;
    }

    /** @return array<int, array{id: int, question_text: string, is_required: bool, sort_order: int}> */
    public function questionsByJobId(int $jobId): array
    {
        $rows = $this->selectAll('SELECT id, question_text, is_required, sort_order FROM job_questions WHERE job_id = ? ORDER BY sort_order ASC', [$jobId]);

        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'question_text' => $r['question_text'],
            'is_required' => (bool) $r['is_required'],
            'sort_order' => (int) $r['sort_order'],
        ], $rows);
    }

    /**
     * Batch-fetches questions for many jobs at once (avoids one query per
     * row when attaching questions to a job list), grouped by job_id.
     *
     * @param int[] $jobIds
     * @return array<int, array<int, array{id: int, question_text: string, is_required: bool, sort_order: int}>>
     */
    public function questionsByJobIds(array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $rows = $this->selectAll("SELECT job_id, id, question_text, is_required, sort_order FROM job_questions WHERE job_id IN ($placeholders) ORDER BY job_id ASC, sort_order ASC", $jobIds);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(int) $r['job_id']][] = [
                'id' => (int) $r['id'],
                'question_text' => $r['question_text'],
                'is_required' => (bool) $r['is_required'],
                'sort_order' => (int) $r['sort_order'],
            ];
        }

        return $grouped;
    }

    /**
     * The current Employer/Superadmin job forms send a `questions` array
     * directly. Any other caller (older tests, scripts) that still only
     * sets the legacy single-question fields gets that one question
     * carried over automatically, so create()/update() work the same as
     * before this table existed for callers that haven't been updated.
     *
     * @return array<int, array{question_text: string, is_required: bool}>
     */
    private function resolveQuestions(array $data): array
    {
        if (isset($data['questions'])) {
            return $data['questions'];
        }

        $text = trim((string) ($data['employer_question'] ?? ''));

        return $text !== '' ? [['question_text' => $text, 'is_required' => !empty($data['employer_question_required'])]] : [];
    }

    /**
     * Replaces every question on a job with the given list — simplest
     * correct semantics for an editable list where rows can be added,
     * removed, and reordered freely between saves. Existing answers
     * referencing a removed question are cascade-deleted with it
     * (application_answers.job_question_id -> job_questions.id ON DELETE
     * CASCADE); this only matters for questions actually removed from the
     * form, not ones merely re-saved with the same text.
     *
     * @param array<int, array{question_text: string, is_required: bool}> $questions
     */
    public function replaceQuestions(int $jobId, array $questions): void
    {
        $this->runDelete('DELETE FROM job_questions WHERE job_id = ?', [$jobId]);

        foreach (array_values($questions) as $i => $q) {
            $text = trim($q['question_text']);
            if ($text === '') {
                continue;
            }
            $this->insert('INSERT INTO job_questions (job_id, question_text, is_required, sort_order) VALUES (?, ?, ?, ?)', [
                $jobId, $text, !empty($q['is_required']) ? 1 : 0, $i,
            ]);
        }
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

        $job['questions'] = $this->questionsByJobId($jobId);

        $company = $this->selectOne('SELECT company_logo, company_name, company_location, contact_email, contact_number, nature_of_business, industry_type, accreditation_status FROM employer WHERE user_id = ? LIMIT 1', [$job['employer_id']]);
        if ($company && $company['company_logo']) {
            $company['company_logo'] = basename($company['company_logo']);
        }

        return ['jobDetails' => $job, 'companyDetails' => $company ?: []];
    }

    /** Attaches each job's `questions` array in one batch query rather than one query per row. */
    private function attachQuestions(array $jobs): array
    {
        $jobIds = array_map(static fn (array $j) => (int) $j['job_id'], $jobs);
        $byJobId = $this->questionsByJobIds($jobIds);

        foreach ($jobs as &$job) {
            $job['questions'] = $byJobId[(int) $job['job_id']] ?? [];
        }

        return $jobs;
    }

    public function allWithEmployer(): array
    {
        $jobs = $this->selectAll('SELECT j.job_id, j.employer_id, j.title, j.location, j.type, j.work_setup, j.classification, j.salary,
                       j.description, j.requirements, j.qualifications, j.status, j.employer_question, j.employer_question_required, j.created_at,
                       e.company_name, e.company_logo
                FROM jobs j
                LEFT JOIN employer e ON j.employer_id = e.user_id
                ORDER BY j.created_at DESC, j.job_id DESC');

        return $this->attachQuestions($jobs);
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

        return $this->attachQuestions($this->selectAll($sql, $params));
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
        $jobs = $this->selectAll('SELECT j.job_id, j.employer_id, j.title, j.location, j.type, j.work_setup, j.classification, j.salary,
                       j.description, j.requirements, j.qualifications, j.status, j.employer_question, j.employer_question_required, j.created_at,
                       e.company_name, e.company_logo
                FROM jobs j
                LEFT JOIN employer e ON j.employer_id = e.user_id
                WHERE j.status = "Active"
                ORDER BY j.created_at DESC, j.job_id DESC');

        return $this->attachQuestions($jobs);
    }

    /** Auto-closes postings older than 30 days without touching application/interview history. */
    public function closeExpired(int $maxAgeDays = 30): int
    {
        return $this->runUpdate("UPDATE jobs SET status = 'Closed' WHERE status = 'Active' AND created_at <= (NOW() - INTERVAL ? DAY)", [$maxAgeDays]);
    }

    public function whereEmployer(int $employerId): array
    {
        $jobs = $this->selectAll('SELECT job_id, employer_id, title, location, type, work_setup, classification, salary,
                       description, requirements, qualifications, status, employer_question, employer_question_required, created_at
                FROM jobs WHERE employer_id = ? ORDER BY created_at DESC, job_id DESC', [$employerId]);

        return $this->attachQuestions($jobs);
    }

    public function create(array $data): int
    {
        date_default_timezone_set('Asia/Manila');
        $createdAt = date('Y-m-d H:i:s');

        try {
            $jobId = $this->insertGetId('INSERT INTO jobs (employer_id, title, type, work_setup, classification, location, salary, status, created_at, description, requirements, qualifications, employer_question, employer_question_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $data['employer_id'], $data['title'], $data['type'], $data['work_setup'], $data['classification'],
                $data['location'], $data['salary'], $data['status'], $createdAt, $data['description'],
                $data['requirements'], $data['qualifications'], $data['employer_question'] ?? '', $data['employer_question_required'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Insert failed: '.$e->getMessage());
        }

        $this->replaceQuestions($jobId, $this->resolveQuestions($data));

        return $jobId;
    }

    public function update(int $jobId, array $data): void
    {
        try {
            $this->runUpdate('UPDATE jobs SET employer_id=?, title=?, type=?, work_setup=?, classification=?, location=?, salary=?, status=?, description=?, requirements=?, qualifications=?, employer_question=?, employer_question_required=? WHERE job_id=?', [
                $data['employer_id'], $data['title'], $data['type'], $data['work_setup'], $data['classification'],
                $data['location'], $data['salary'], $data['status'], $data['description'], $data['requirements'],
                $data['qualifications'], $data['employer_question'] ?? '', $data['employer_question_required'] ?? 0, $jobId,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Update failed: '.$e->getMessage());
        }

        $this->replaceQuestions($jobId, $this->resolveQuestions($data));
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
        // Verified by ownership, not by the UPDATE's affected-row count —
        // that count is 0 whenever none of the listed columns actually
        // changed value (e.g. only the questions list changed), which would
        // otherwise make a legitimate save look like "job not found".
        if ($this->titleByIdForEmployer($jobId, $employerId) === null) {
            return false;
        }

        $this->runUpdate('UPDATE jobs SET title=?, type=?, work_setup=?, classification=?, location=?, salary=?, status=?, description=?, requirements=?, qualifications=?, employer_question=?, employer_question_required=? WHERE job_id=? AND employer_id=?', [
            $data['title'], $data['type'], $data['work_setup'], $data['classification'], $data['location'],
            $data['salary'], $data['status'], $data['description'], $data['requirements'], $data['qualifications'],
            $data['employer_question'] ?? '', $data['employer_question_required'] ?? 0, $jobId, $employerId,
        ]);

        $this->replaceQuestions($jobId, $this->resolveQuestions($data));

        return true;
    }

    public function deleteForEmployer(int $jobId, int $employerId): bool
    {
        return $this->runDelete('DELETE FROM jobs WHERE job_id=? AND employer_id=?', [$jobId, $employerId]) > 0;
    }
}
