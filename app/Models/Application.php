<?php

namespace App\Models;

use App\Concerns\LegacyQueries;
use Illuminate\Database\QueryException;

/** Ported from backend/Models/Application.php — see User.php's docblock for the porting approach. */
class Application
{
    use LegacyQueries;

    public function existsForAlumniJob(int $alumniId, int $jobId): bool
    {
        return $this->selectOne('SELECT application_id FROM applications WHERE alumni_id = ? AND job_id = ?', [$alumniId, $jobId]) !== null;
    }

    /**
     * @param array<int, string> $answers job_question_id => answer text.
     *        $applicationAnswer is kept only for the single-question legacy
     *        column, mirrored from answers[0] by the caller if present.
     */
    public function createForAlumni(int $alumniId, int $jobId, ?string $coverLetterText = null, ?string $coverLetterFile = null, ?string $applicationAnswer = null, array $answers = []): bool
    {
        try {
            $applicationId = $this->insertGetId('INSERT INTO applications (alumni_id, job_id, cover_letter_text, cover_letter_file, application_answer) VALUES (?, ?, ?, ?, ?)', [$alumniId, $jobId, $coverLetterText, $coverLetterFile, $applicationAnswer]);
        } catch (QueryException $e) {
            // Duplicate (alumni_id, job_id) — existsForAlumniJob() in the
            // caller already handles the normal "already applied" case;
            // this only fires on a genuine race between two near-
            // simultaneous requests, which the alumni_job_unique
            // constraint is the actual DB-level guarantee against.
            return false;
        }

        foreach ($answers as $questionId => $answerText) {
            $answerText = trim((string) $answerText);
            if ($answerText === '') {
                continue;
            }
            $this->insert('INSERT INTO application_answers (application_id, job_question_id, answer_text) VALUES (?, ?, ?)', [
                $applicationId, (int) $questionId, $answerText,
            ]);
        }

        return true;
    }

    /** @return array<int, array{question_id: int, question_text: string, is_required: bool, answer_text: string}> */
    public function answersForApplication(int $applicationId): array
    {
        $rows = $this->selectAll('SELECT jq.id AS question_id, jq.question_text, jq.is_required, aa.answer_text
            FROM application_answers aa
            JOIN job_questions jq ON aa.job_question_id = jq.id
            WHERE aa.application_id = ?
            ORDER BY jq.sort_order ASC', [$applicationId]);

        return array_map(static fn (array $r) => [
            'question_id' => (int) $r['question_id'],
            'question_text' => $r['question_text'],
            'is_required' => (bool) $r['is_required'],
            'answer_text' => $r['answer_text'],
        ], $rows);
    }

    /**
     * Batch-fetches answers for many applications at once, grouped by
     * application_id — for attaching to a list without one query per row.
     *
     * @param int[] $applicationIds
     * @return array<int, array<int, array{question_id: int, question_text: string, is_required: bool, answer_text: string}>>
     */
    public function answersForApplications(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
        $rows = $this->selectAll("SELECT aa.application_id, jq.id AS question_id, jq.question_text, jq.is_required, aa.answer_text
            FROM application_answers aa
            JOIN job_questions jq ON aa.job_question_id = jq.id
            WHERE aa.application_id IN ($placeholders)
            ORDER BY aa.application_id ASC, jq.sort_order ASC", $applicationIds);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(int) $r['application_id']][] = [
                'question_id' => (int) $r['question_id'],
                'question_text' => $r['question_text'],
                'is_required' => (bool) $r['is_required'],
                'answer_text' => $r['answer_text'],
            ];
        }

        return $grouped;
    }

    public function appliedJobsForAlumni(int $alumniId): array
    {
        $jobs = $this->selectAll('SELECT app.application_id, app.job_id, app.applied_at, app.status AS application_status,
                   app.cover_letter_text, app.cover_letter_file, app.application_answer,
                   j.title, j.type, j.location, j.salary, j.status, j.created_at, j.description,
                   j.requirements, j.qualifications, j.employer_question, j.employer_question_required, j.employer_id
            FROM applications app JOIN jobs j ON app.job_id = j.job_id
            WHERE app.alumni_id = ? ORDER BY app.applied_at DESC', [$alumniId]);

        if (empty($jobs)) {
            return [];
        }

        $answersByApplication = $this->answersForApplications(array_map(static fn ($j) => (int) $j['application_id'], $jobs));
        foreach ($jobs as &$job) {
            $job['answers'] = $answersByApplication[(int) $job['application_id']] ?? [];
        }
        unset($job);

        $employerIds = array_values(array_unique(array_filter(array_map(fn ($j) => (int) $j['employer_id'], $jobs))));
        $companies = [];
        if (!empty($employerIds)) {
            $placeholders = implode(',', array_fill(0, count($employerIds), '?'));
            foreach ($this->selectAll("SELECT user_id, company_name, company_logo, company_location, contact_email, contact_number, nature_of_business, industry_type, accreditation_status FROM employer WHERE user_id IN ($placeholders)", $employerIds) as $row) {
                $companies[(int) $row['user_id']] = $row;
            }
        }

        foreach ($jobs as &$job) {
            $company = $companies[(int) $job['employer_id']] ?? [];
            $job['company_name'] = $company['company_name'] ?? '';
            $job['company_logo'] = $company['company_logo'] ?? '';
            $job['company_location'] = $company['company_location'] ?? '';
            $job['contact_email'] = $company['contact_email'] ?? '';
            $job['contact_number'] = $company['contact_number'] ?? '';
            $job['nature_of_business'] = $company['nature_of_business'] ?? '';
            $job['industry_type'] = $company['industry_type'] ?? '';
            $job['accreditation_status'] = $company['accreditation_status'] ?? '';
        }

        return $jobs;
    }

    public function deleteForAlumni(int $alumniId, int $jobId): bool
    {
        return $this->runDelete('DELETE FROM applications WHERE alumni_id = ? AND job_id = ?', [$alumniId, $jobId]) >= 0;
    }

    public function isHiredByEmployer(int $alumniId, int $employerId): bool
    {
        return $this->selectOne("SELECT app.status FROM applications app
            JOIN jobs j ON app.job_id = j.job_id
            WHERE app.alumni_id = ? AND j.employer_id = ? AND app.status = 'Hired' LIMIT 1", [$alumniId, $employerId]) !== null;
    }

    public function hiredEmployerIdsForAlumni(int $alumniId): array
    {
        $rows = $this->selectAll('SELECT DISTINCT j.employer_id FROM applications app
            JOIN jobs j ON app.job_id = j.job_id
            WHERE app.alumni_id = ? AND app.status = "Hired"', [$alumniId]);

        return array_map(static fn ($row) => (int) $row['employer_id'], $rows);
    }

    public function statusCountsForEmployer(int $employerUserId): array
    {
        $row = $this->selectOne('SELECT
                COUNT(CASE WHEN status = "Pending" THEN 1 END) as pending,
                COUNT(CASE WHEN status = "Interview" THEN 1 END) as interview,
                COUNT(CASE WHEN status = "Hired" THEN 1 END) as hired,
                COUNT(CASE WHEN status = "Rejected" THEN 1 END) as rejected
            FROM applications
            WHERE job_id IN (SELECT job_id FROM jobs WHERE employer_id = ?)', [$employerUserId]);

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'interview' => (int) ($row['interview'] ?? 0),
            'hired' => (int) ($row['hired'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
        ];
    }

    /**
     * All applications for an employer's jobs, each enriched with alumni
     * profile pic/resume/experience/education/skills — batched via IN(...)
     * queries instead of an N+1-per-row pattern.
     */
    public function allForEmployerWithDetails(int $employerUserId): array
    {
        $applications = $this->selectAll('SELECT app.application_id, app.applied_at, app.status AS application_status,
                app.cover_letter_text, app.cover_letter_file, app.application_answer,
                a.alumni_id, a.first_name, a.middle_name, a.last_name, a.birthdate, a.contact, a.gender,
                a.civil_status, a.city, a.province, a.year_graduated, a.college, a.course,
                a.campus_id, camp.name AS campus_name,
                u.email, u.secondary_email,
                j.job_id, j.title, j.type, j.location, j.salary, j.status AS job_status, j.created_at,
                j.description, j.requirements, j.qualifications, j.employer_question, j.employer_question_required, j.employer_id,
                e.company_name
            FROM applications app
            JOIN alumni a ON app.alumni_id = a.alumni_id
            JOIN user u ON a.user_id = u.user_id
            JOIN jobs j ON app.job_id = j.job_id
            LEFT JOIN employer e ON j.employer_id = e.user_id
            LEFT JOIN campus camp ON a.campus_id = camp.campus_id
            WHERE j.employer_id = ?
            ORDER BY app.applied_at DESC', [$employerUserId]);

        if (empty($applications)) {
            return [];
        }

        foreach ($applications as &$row) {
            $row['alumni_name'] = trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']);
        }
        unset($row);

        $alumniIds = array_values(array_unique(array_map(fn ($r) => (int) $r['alumni_id'], $applications)));
        $placeholders = implode(',', array_fill(0, count($alumniIds), '?'));

        $profilePics = [];
        foreach ($this->selectAll("SELECT alumni_id, profile_pic FROM alumni WHERE alumni_id IN ($placeholders)", $alumniIds) as $row) {
            $profilePics[(int) $row['alumni_id']] = $row['profile_pic'];
        }
        $resumes = $this->latestResumeByAlumni($placeholders, $alumniIds);
        $experiences = $this->groupedByAlumni("SELECT alumni_id, experience_id, title, company, start_date, end_date, current, description, created_at, updated_at FROM alumni_experience WHERE alumni_id IN ($placeholders) ORDER BY start_date DESC", $alumniIds);
        $educations = $this->groupedByAlumni("SELECT alumni_id, education_id, degree, school, start_date, end_date, current, created_at FROM alumni_education WHERE alumni_id IN ($placeholders) ORDER BY start_date DESC", $alumniIds);
        $skills = $this->groupedByAlumni("SELECT alumni_id, skill_id, name, certificate, certificate_file, created_at FROM alumni_skill WHERE alumni_id IN ($placeholders)", $alumniIds);
        $certifications = $this->groupedByAlumni("SELECT alumni_id, certification_id, name, issuer, issue_date, certificate_file FROM alumni_certification WHERE alumni_id IN ($placeholders) ORDER BY issue_date DESC", $alumniIds);

        foreach ($applications as &$row) {
            $alumniId = (int) $row['alumni_id'];
            $pic = $profilePics[$alumniId] ?? null;
            $row['profile_image'] = $pic ? 'uploads/profile_picture/'.$pic : null;
            $resumeFile = $resumes[$alumniId] ?? null;
            $row['resume_file'] = $resumeFile ? 'uploads/resumes/'.$resumeFile : null;
            $row['experiences'] = $experiences[$alumniId] ?? [];
            $row['educations'] = $educations[$alumniId] ?? [];
            $row['skills'] = $skills[$alumniId] ?? [];
            $row['certifications'] = $certifications[$alumniId] ?? [];
        }
        unset($row);

        $answersByApplication = $this->answersForApplications(array_map(static fn ($r) => (int) $r['application_id'], $applications));
        foreach ($applications as &$row) {
            $row['answers'] = $answersByApplication[(int) $row['application_id']] ?? [];
        }

        return $applications;
    }

    public function updateStatusForEmployer(int $applicationId, int $employerUserId, string $status): bool
    {
        $row = $this->selectOne('SELECT app.alumni_id, app.job_id, j.employer_id
            FROM applications app JOIN jobs j ON app.job_id = j.job_id
            WHERE app.application_id = ? LIMIT 1', [$applicationId]);

        if (!$row || (int) $row['employer_id'] !== $employerUserId) {
            return false;
        }

        return $this->runUpdate('UPDATE applications SET status = ? WHERE application_id = ?', [$status, $applicationId]) >= 0;
    }

    public function alumniUserIdForApplication(int $applicationId): ?int
    {
        $row = $this->selectOne('SELECT a.user_id FROM applications app JOIN alumni a ON app.alumni_id = a.alumni_id WHERE app.application_id = ? LIMIT 1', [$applicationId]);

        return $row ? (int) $row['user_id'] : null;
    }

    public function jobIdForApplication(int $applicationId): ?int
    {
        $row = $this->selectOne('SELECT job_id FROM applications WHERE application_id = ? LIMIT 1', [$applicationId]);

        return $row ? (int) $row['job_id'] : null;
    }

    private function latestResumeByAlumni(string $placeholders, array $ids): array
    {
        $out = [];
        foreach ($this->selectAll("SELECT alumni_id, file_name, uploaded_at FROM alumni_resume WHERE alumni_id IN ($placeholders) ORDER BY uploaded_at DESC", $ids) as $row) {
            $alumniId = (int) $row['alumni_id'];
            if (!isset($out[$alumniId])) {
                $out[$alumniId] = $row['file_name'];
            }
        }

        return $out;
    }

    private function groupedByAlumni(string $sql, array $ids): array
    {
        $out = [];
        foreach ($this->selectAll($sql, $ids) as $row) {
            $out[(int) $row['alumni_id']][] = $row;
        }

        return $out;
    }

    public function candidatesForEmployer(int $employerUserId): array
    {
        $rows = $this->selectAll("SELECT a.application_id, a.alumni_id, a.job_id,
                   al.first_name, al.middle_name, al.last_name, u.email,
                   j.title as job_title
            FROM applications a
            JOIN alumni al ON a.alumni_id = al.alumni_id
            JOIN user u ON al.user_id = u.user_id
            JOIN jobs j ON a.job_id = j.job_id
            WHERE j.employer_id = ? AND a.status = 'Interview'
            ORDER BY a.applied_at DESC", [$employerUserId]);

        return array_map(function ($row) {
            $row['alumni_name'] = trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']);

            return $row;
        }, $rows);
    }

    public function hiredCountForEmployer(int $employerUserId): int
    {
        $row = $this->selectOne("SELECT COUNT(*) as total FROM applications app
            JOIN jobs j ON app.job_id = j.job_id
            WHERE j.employer_id = ? AND LOWER(TRIM(app.status)) = 'hired'", [$employerUserId]);

        return (int) ($row['total'] ?? 0);
    }
}
