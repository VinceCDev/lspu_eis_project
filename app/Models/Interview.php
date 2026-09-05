<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/Interview.php — see User.php's docblock for the porting approach. */
class Interview
{
    use LegacyQueries;

    public function countForEmployer(int $employerUserId): int
    {
        return (int) ($this->selectOne('SELECT COUNT(*) as total FROM interviews WHERE employer_id = ?', [$employerUserId])['total'] ?? 0);
    }

    public function allForEmployer(int $employerUserId): array
    {
        $rows = $this->selectAll('SELECT i.*, a.first_name, a.middle_name, a.last_name, a.contact, u.email,
                   (SELECT profile_pic FROM alumni WHERE alumni_id = i.alumni_id) as profile_image
            FROM interviews i
            JOIN alumni a ON i.alumni_id = a.alumni_id
            JOIN user u ON a.user_id = u.user_id
            WHERE i.employer_id = ?
            ORDER BY i.interview_date DESC', [$employerUserId]);

        return array_map(function ($row) {
            $row['alumni_name'] = trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']);
            $row['profile_image'] = $row['profile_image'] ? 'uploads/profile_picture/'.$row['profile_image'] : null;

            return $row;
        }, $rows);
    }

    public function existsForApplication(int $applicationId): bool
    {
        return $this->selectOne('SELECT interview_id FROM interviews WHERE application_id = ? LIMIT 1', [$applicationId]) !== null;
    }

    /**
     * Schedules an interview for an application, verifying the application's
     * job actually belongs to this employer first.
     */
    public function scheduleForApplication(int $applicationId, int $employerUserId, array $data): ?array
    {
        $application = $this->selectOne('SELECT job_id, alumni_id FROM applications WHERE application_id = ?', [$applicationId]);
        if (!$application) {
            return null;
        }

        $job = $this->selectOne('SELECT employer_id FROM jobs WHERE job_id = ?', [$application['job_id']]);
        if (!$job || (int) $job['employer_id'] !== $employerUserId) {
            return null;
        }

        $jobId = (int) $application['job_id'];
        $alumniId = (int) $application['alumni_id'];

        $interviewId = $this->insertGetId('INSERT INTO interviews (application_id, job_id, alumni_id, employer_id, interview_date, duration, interview_type, location, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $applicationId, $jobId, $alumniId, $employerUserId, $data['interview_date'],
            $data['duration'], $data['interview_type'], $data['location'], $data['status'], $data['notes'],
        ]);

        return ['interview_id' => $interviewId, 'alumni_id' => $alumniId, 'job_id' => $jobId];
    }

    public function updateForEmployer(int $interviewId, int $employerUserId, array $fields): bool
    {
        if (!$this->belongsToEmployer($interviewId, $employerUserId)) {
            return false;
        }

        $allowed = ['status', 'interview_date', 'duration', 'interview_type', 'location', 'notes'];
        $set = [];
        $params = [];
        foreach ($allowed as $field) {
            if (isset($fields[$field])) {
                $set[] = "{$field} = ?";
                $params[] = $fields[$field];
            }
        }

        if (empty($set)) {
            return false;
        }

        $params[] = $interviewId;

        return $this->runUpdate('UPDATE interviews SET '.implode(', ', $set).', updated_at = NOW() WHERE interview_id = ?', $params) >= 0;
    }

    public function cancelForEmployer(int $interviewId, int $employerUserId): bool
    {
        if (!$this->belongsToEmployer($interviewId, $employerUserId)) {
            return false;
        }

        return $this->runUpdate("UPDATE interviews SET status = 'Cancelled', updated_at = NOW() WHERE interview_id = ?", [$interviewId]) >= 0;
    }

    private function belongsToEmployer(int $interviewId, int $employerUserId): bool
    {
        return $this->selectOne('SELECT interview_id FROM interviews WHERE interview_id = ? AND employer_id = ?', [$interviewId, $employerUserId]) !== null;
    }

    public function forEmployerOnDate(int $employerUserId, string $date): array
    {
        $rows = $this->selectAll('SELECT i.*, a.first_name, a.middle_name, a.last_name, a.contact, u.email, j.title,
                   (SELECT profile_pic FROM alumni WHERE alumni_id = i.alumni_id) as profile_image
            FROM interviews i
            JOIN alumni a ON i.alumni_id = a.alumni_id
            JOIN user u ON a.user_id = u.user_id
            JOIN jobs j ON i.job_id = j.job_id
            WHERE i.employer_id = ? AND DATE(i.interview_date) = ?
            ORDER BY i.interview_date ASC', [$employerUserId, $date]);

        return array_map(function ($row) {
            $row['alumni_name'] = trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']);
            $row['profile_image'] = $row['profile_image'] ? 'uploads/profile_picture/'.$row['profile_image'] : null;

            $dt = new \DateTime($row['interview_date']);
            $row['formatted_time'] = $dt->format('g:i A');
            $row['formatted_date'] = $dt->format('M j, Y');

            return $row;
        }, $rows);
    }
}
