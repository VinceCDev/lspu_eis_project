<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/JobMatch.php — see User.php's docblock for the porting approach. */
class JobMatch
{
    use LegacyQueries;

    /**
     * Employer's job-match leaderboard, with alumni detail (skills,
     * experiences, educations, resume) batched via IN(...) queries.
     */
    public function allForEmployer(int $employerUserId): array
    {
        $matches = $this->selectAll('SELECT jml.match_id, jml.alumni_id, jml.job_id, jml.match_percentage, jml.matched_at, jml.notified,
                   a.first_name, a.last_name, a.course, a.year_graduated, a.contact, a.birthdate, a.gender,
                   a.civil_status, a.college, a.profile_pic,
                   j.title as job_title, j.type as job_type, j.location as job_location, j.salary as job_salary,
                   e.company_name, u.email as alumni_email
            FROM job_match_leaderboard jml
            JOIN alumni a ON jml.alumni_id = a.alumni_id
            JOIN user u ON a.user_id = u.user_id
            JOIN jobs j ON jml.job_id = j.job_id
            JOIN employer e ON j.employer_id = e.user_id
            WHERE j.employer_id = ?
              AND EXISTS (
                  SELECT 1 FROM applications app
                  WHERE app.alumni_id = jml.alumni_id AND app.job_id = jml.job_id
              )
            ORDER BY jml.match_percentage DESC, jml.matched_at DESC', [$employerUserId]);

        foreach ($matches as &$row) {
            $row['email'] = $row['alumni_email'];
            unset($row['alumni_email']);
        }
        unset($row);

        if (empty($matches)) {
            return [];
        }

        $alumniIds = array_values(array_unique(array_map(fn ($r) => (int) $r['alumni_id'], $matches)));
        $placeholders = implode(',', array_fill(0, count($alumniIds), '?'));

        $skills = $this->groupedByAlumni("SELECT alumni_id, skill_id, name, certificate, certificate_file FROM alumni_skill WHERE alumni_id IN ($placeholders)", $alumniIds);
        $experiences = $this->groupedByAlumni("SELECT alumni_id, experience_id, title, company, start_date, end_date, current, description FROM alumni_experience WHERE alumni_id IN ($placeholders) ORDER BY start_date DESC", $alumniIds);
        $educations = $this->groupedByAlumni("SELECT alumni_id, education_id, degree, school, start_date, end_date, current FROM alumni_education WHERE alumni_id IN ($placeholders) ORDER BY start_date DESC", $alumniIds);
        $resumes = $this->latestResumeByAlumni($placeholders, $alumniIds);

        foreach ($matches as &$row) {
            $alumniId = (int) $row['alumni_id'];
            $row['skills'] = $skills[$alumniId] ?? [];
            $row['experiences'] = $experiences[$alumniId] ?? [];
            $row['educations'] = $educations[$alumniId] ?? [];
            $row['file_name'] = $resumes[$alumniId] ?? null;

            $picPath = $row['profile_pic'] ? 'uploads/profile_picture/'.$row['profile_pic'] : null;
            $row['profile_pic'] = ($picPath && file_exists($picPath)) ? 'uploads/profile_picture/'.$row['profile_pic'] : null;
        }

        return $matches;
    }

    private function groupedByAlumni(string $sql, array $ids): array
    {
        $out = [];
        foreach ($this->selectAll($sql, $ids) as $row) {
            $out[(int) $row['alumni_id']][] = $row;
        }

        return $out;
    }

    private function latestResumeByAlumni(string $placeholders, array $ids): array
    {
        $out = [];
        foreach ($this->selectAll("SELECT alumni_id, file_name FROM alumni_resume WHERE alumni_id IN ($placeholders) ORDER BY uploaded_at DESC", $ids) as $row) {
            $alumniId = (int) $row['alumni_id'];
            if (!isset($out[$alumniId])) {
                $out[$alumniId] = $row['file_name'];
            }
        }

        return $out;
    }
}
