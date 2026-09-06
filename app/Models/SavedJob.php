<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/SavedJob.php — see User.php's docblock for the porting approach. */
class SavedJob
{
    use LegacyQueries;

    public function idsForUser(int $userId): array
    {
        $rows = $this->selectAll('SELECT job_id FROM saved_jobs WHERE user_id = ?', [$userId]);

        return array_map(static fn ($row) => (int) $row['job_id'], $rows);
    }

    public function withJobDetailsForUser(int $userId): array
    {
        $saved = [];
        foreach ($this->selectAll('SELECT job_id, saved_at FROM saved_jobs WHERE user_id = ?', [$userId]) as $row) {
            $saved[(int) $row['job_id']] = $row['saved_at'];
        }

        if (empty($saved)) {
            return [];
        }

        $jobIds = array_keys($saved);
        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $jobs = $this->selectAll("SELECT * FROM jobs WHERE job_id IN ($placeholders)", $jobIds);

        $employerIds = array_values(array_unique(array_filter(array_map(fn ($j) => (int) $j['employer_id'], $jobs))));
        $companies = [];
        if (!empty($employerIds)) {
            $ePlaceholders = implode(',', array_fill(0, count($employerIds), '?'));
            foreach ($this->selectAll("SELECT user_id, company_name, company_logo, company_location, contact_email, contact_number, nature_of_business, industry_type, accreditation_status FROM employer WHERE user_id IN ($ePlaceholders)", $employerIds) as $row) {
                $uid = (int) $row['user_id'];
                unset($row['user_id']);
                $companies[$uid] = $row;
            }
        }

        $questionsByJob = (new Job())->questionsByJobIds($jobIds);

        $savedJobs = [];
        foreach ($jobs as $job) {
            $companyDetails = $companies[(int) $job['employer_id']] ?? [];
            $job['savedDate'] = $saved[(int) $job['job_id']] ?? null;
            $job['company_name'] = $companyDetails['company_name'] ?? '';
            $job['companyDetails'] = $companyDetails;
            $job['questions'] = $questionsByJob[(int) $job['job_id']] ?? [];
            $savedJobs[] = $job;
        }

        return $savedJobs;
    }

    public function save(int $userId, int $jobId): bool
    {
        return $this->insert('INSERT IGNORE INTO saved_jobs (user_id, job_id) VALUES (?, ?)', [$userId, $jobId]);
    }

    public function unsave(int $userId, int $jobId): bool
    {
        return $this->runDelete('DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?', [$userId, $jobId]) >= 0;
    }
}
