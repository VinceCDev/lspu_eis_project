<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/Applicant.php — see User.php's docblock for the porting approach. */
class Applicant
{
    use LegacyQueries;

    public function allWithAlumniAndJob(): array
    {
        $sql = "SELECT
                    app.application_id, app.applied_at, app.status AS application_status,
                    app.cover_letter_text, app.cover_letter_file, app.application_answer,
                    a.alumni_id, a.first_name, a.middle_name, a.last_name, a.birthdate, a.contact, a.gender, a.civil_status, a.city, a.province, a.year_graduated, a.college, a.course,
                    a.campus_id, c.name AS campus_name,
                    (SELECT file_name FROM alumni_resume ar WHERE ar.alumni_id = a.alumni_id ORDER BY ar.uploaded_at DESC LIMIT 1) AS resume_file,
                    u.email, u.secondary_email,
                    j.job_id, j.title, j.type, j.location, j.salary, j.status AS job_status, j.created_at, j.description, j.requirements, j.qualifications, j.employer_question, j.employer_question_required, j.employer_id,
                    e.company_name
                FROM applications app
                JOIN alumni a ON app.alumni_id = a.alumni_id
                JOIN user u ON a.user_id = u.user_id
                JOIN jobs j ON app.job_id = j.job_id
                LEFT JOIN employer e ON j.employer_id = e.user_id
                LEFT JOIN campus c ON a.campus_id = c.campus_id
                ORDER BY app.applied_at DESC";

        $rows = array_map(function ($row) {
            $row['alumni_name'] = trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']);

            return $row;
        }, $this->selectAll($sql));

        return $this->attachCertifications($rows);
    }

    public function allWithAlumniAndJobForCampus(int $campusId): array
    {
        $sql = "SELECT
                    app.application_id, app.applied_at, app.status AS application_status,
                    app.cover_letter_text, app.cover_letter_file, app.application_answer,
                    a.alumni_id, a.first_name, a.middle_name, a.last_name, a.birthdate, a.contact, a.gender, a.civil_status, a.city, a.province, a.year_graduated, a.college, a.course,
                    a.campus_id, c.name AS campus_name,
                    (SELECT file_name FROM alumni_resume ar WHERE ar.alumni_id = a.alumni_id ORDER BY ar.uploaded_at DESC LIMIT 1) AS resume_file,
                    u.email, u.secondary_email,
                    j.job_id, j.title, j.type, j.location, j.salary, j.status AS job_status, j.created_at, j.description, j.requirements, j.qualifications, j.employer_question, j.employer_question_required, j.employer_id,
                    e.company_name
                FROM applications app
                JOIN alumni a ON app.alumni_id = a.alumni_id
                JOIN user u ON a.user_id = u.user_id
                JOIN jobs j ON app.job_id = j.job_id
                LEFT JOIN employer e ON j.employer_id = e.user_id
                LEFT JOIN campus c ON a.campus_id = c.campus_id
                WHERE a.campus_id = ?
                ORDER BY app.applied_at DESC";

        $rows = array_map(function ($row) {
            $row['alumni_name'] = trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']);

            return $row;
        }, $this->selectAll($sql, [$campusId]));

        return $this->attachCertifications($rows);
    }

    /** Attaches each row's work experience, education, skills, certifications, and Q&A answers. */
    private function attachCertifications(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $alumniIds = array_values(array_unique(array_map(fn ($r) => (int) $r['alumni_id'], $rows)));
        $placeholders = implode(',', array_fill(0, count($alumniIds), '?'));

        $experiences = $this->groupedByAlumni("SELECT alumni_id, experience_id, title, company, start_date, end_date, current, description FROM alumni_experience WHERE alumni_id IN ($placeholders) ORDER BY start_date DESC", $alumniIds);
        $educations = $this->groupedByAlumni("SELECT alumni_id, education_id, degree, school, start_date, end_date, current FROM alumni_education WHERE alumni_id IN ($placeholders) ORDER BY start_date DESC", $alumniIds);
        $skills = $this->groupedByAlumni("SELECT alumni_id, skill_id, name, certificate, certificate_file FROM alumni_skill WHERE alumni_id IN ($placeholders)", $alumniIds);
        $certifications = $this->groupedByAlumni("SELECT alumni_id, certification_id, name, issuer, issue_date, certificate_file FROM alumni_certification WHERE alumni_id IN ($placeholders) ORDER BY issue_date DESC", $alumniIds);
        $answersByApplication = (new Application())->answersForApplications(array_map(static fn ($r) => (int) $r['application_id'], $rows));

        foreach ($rows as &$row) {
            $alumniId = (int) $row['alumni_id'];
            $row['experiences'] = $experiences[$alumniId] ?? [];
            $row['educations'] = $educations[$alumniId] ?? [];
            $row['skills'] = $skills[$alumniId] ?? [];
            $row['certifications'] = $certifications[$alumniId] ?? [];
            $row['answers'] = $answersByApplication[(int) $row['application_id']] ?? [];
        }

        return $rows;
    }

    private function groupedByAlumni(string $sql, array $ids): array
    {
        $grouped = [];
        foreach ($this->selectAll($sql, $ids) as $row) {
            $grouped[(int) $row['alumni_id']][] = $row;
        }

        return $grouped;
    }

    public function delete(int $applicationId): bool
    {
        return $this->runDelete('DELETE FROM applications WHERE application_id = ?', [$applicationId]) >= 0;
    }

    public function campusIdForApplication(int $applicationId): ?int
    {
        $row = $this->selectOne('SELECT a.campus_id
            FROM applications app JOIN alumni a ON app.alumni_id = a.alumni_id
            WHERE app.application_id = ? LIMIT 1', [$applicationId]);

        return $row && $row['campus_id'] !== null ? (int) $row['campus_id'] : null;
    }
}
