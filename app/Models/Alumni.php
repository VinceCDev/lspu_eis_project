<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/Alumni.php — see User.php's docblock for the porting approach. */
class Alumni
{
    use LegacyQueries;

    public function allWithSkills(): array
    {
        $rows = $this->selectAll('SELECT a.user_id, a.course, a.alumni_id, a.first_name, a.last_name FROM alumni a');
        if (empty($rows)) {
            return [];
        }

        $alumniIds = array_column($rows, 'alumni_id');
        $placeholders = implode(',', array_fill(0, count($alumniIds), '?'));
        $skillsByAlumniId = [];
        foreach ($this->selectAll("SELECT alumni_id, name FROM alumni_skill WHERE alumni_id IN ($placeholders)", $alumniIds) as $skillRow) {
            $skillsByAlumniId[$skillRow['alumni_id']][] = $skillRow['name'];
        }

        $alumni = [];
        foreach ($rows as $row) {
            $alumni[] = [
                'user_id' => $row['user_id'],
                'alumni_id' => $row['alumni_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'course' => $row['course'],
                'skills' => implode(', ', $skillsByAlumniId[$row['alumni_id']] ?? []),
            ];
        }

        return $alumni;
    }

    public function detailsByUserId(int $userId): array
    {
        $user = $this->selectOne('SELECT email, secondary_email FROM user WHERE user_id = ? LIMIT 1', [$userId]);
        $email = $user['email'] ?? null;
        $secondaryEmail = $user['secondary_email'] ?? null;

        $a = $this->selectOne('SELECT a.first_name, a.middle_name, a.last_name, a.birthdate, a.contact, a.gender, a.civil_status, a.city, a.province, a.year_graduated, a.college, a.course, a.campus_id, c.name, a.verification_document
            FROM alumni a LEFT JOIN campus c ON a.campus_id = c.campus_id
            WHERE a.user_id = ? LIMIT 1', [$userId]);

        if (!$a) {
            return [
                'name' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '',
                'email' => $email, 'secondary_email' => $secondaryEmail,
                'birthdate' => '', 'contact' => '', 'gender' => '', 'civil_status' => '',
                'city' => '', 'province' => '', 'year_graduated' => '', 'college' => '',
                'course' => '', 'campus_id' => null, 'campus_name' => null, 'verification_document' => '',
            ];
        }

        return [
            'name' => trim($a['first_name'].' '.$a['middle_name'].' '.$a['last_name']),
            'first_name' => $a['first_name'],
            'middle_name' => $a['middle_name'],
            'last_name' => $a['last_name'],
            'email' => $email,
            'secondary_email' => $secondaryEmail,
            'birthdate' => $a['birthdate'],
            'contact' => $a['contact'],
            'gender' => $a['gender'],
            'civil_status' => $a['civil_status'],
            'city' => $a['city'],
            'province' => $a['province'],
            'year_graduated' => $a['year_graduated'],
            'college' => $a['college'],
            'course' => $a['course'],
            'campus_id' => $a['campus_id'],
            'campus_name' => $a['name'],
            'verification_document' => $a['verification_document'],
        ];
    }

    public function alumniIdByUserId(int $userId): ?int
    {
        $row = $this->selectOne('SELECT alumni_id FROM alumni WHERE user_id = ? LIMIT 1', [$userId]);

        return $row ? (int) $row['alumni_id'] : null;
    }

    public function educationByUserId(int $userId): array
    {
        $alumniId = $this->alumniIdByUserId($userId);

        return $alumniId ? $this->educationByAlumniId($alumniId) : [];
    }

    public function educationByAlumniId(int $alumniId): array
    {
        $rows = $this->selectAll('SELECT education_id, degree, school, start_date, end_date, current FROM alumni_education WHERE alumni_id = ? ORDER BY start_date DESC', [$alumniId]);

        return array_map(static fn ($row) => $row + ['current' => (bool) $row['current']], $rows);
    }

    public function skillsByUserId(int $userId): array
    {
        $alumniId = $this->alumniIdByUserId($userId);

        return $alumniId ? $this->skillsByAlumniId($alumniId) : [];
    }

    public function skillsByAlumniId(int $alumniId): array
    {
        return $this->selectAll('SELECT skill_id, name, certificate, certificate_file FROM alumni_skill WHERE alumni_id = ?', [$alumniId]);
    }

    public function certificationsByUserId(int $userId): array
    {
        $alumniId = $this->alumniIdByUserId($userId);
        if (!$alumniId) {
            return [];
        }

        return $this->selectAll('SELECT certification_id, name, issuer, issue_date, certificate_file FROM alumni_certification WHERE alumni_id = ? ORDER BY issue_date DESC', [$alumniId]);
    }

    public function experienceByUserId(int $userId): array
    {
        $alumniId = $this->alumniIdByUserId($userId);

        return $alumniId ? $this->experienceByAlumniId($alumniId) : [];
    }

    public function experienceByAlumniId(int $alumniId): array
    {
        $rows = $this->selectAll('SELECT experience_id, title, company, start_date, end_date, current, description, location_of_work, employment_status, employment_sector FROM alumni_experience WHERE alumni_id = ? ORDER BY start_date DESC', [$alumniId]);

        return array_map(static fn ($row) => $row + ['current' => (bool) $row['current']], $rows);
    }

    public function resumeByUserId(int $userId): ?array
    {
        $alumniId = $this->alumniIdByUserId($userId);

        return $alumniId ? $this->resumeByAlumniId($alumniId) : null;
    }

    public function resumeByAlumniId(int $alumniId): ?array
    {
        return $this->selectOne('SELECT resume_id, file_name, uploaded_at FROM alumni_resume WHERE alumni_id = ? ORDER BY uploaded_at DESC LIMIT 1', [$alumniId]);
    }

    /**
     * Batched skills/education/experience/resume lookups for a set of alumni
     * IDs — one query each instead of one query per alumni.
     *
     * @param int[] $alumniIds
     * @return array{skills: array<int, array>, education: array<int, array>, experience: array<int, array>, resume: array<int, array>}
     */
    public function detailsForAlumniIds(array $alumniIds): array
    {
        if (empty($alumniIds)) {
            return ['skills' => [], 'education' => [], 'experience' => [], 'resume' => []];
        }

        $placeholders = implode(',', array_fill(0, count($alumniIds), '?'));

        $skills = $this->groupedByAlumniId("SELECT alumni_id, skill_id, name, certificate, certificate_file FROM alumni_skill WHERE alumni_id IN ($placeholders)", $alumniIds);
        $education = $this->groupedByAlumniId("SELECT alumni_id, education_id, degree, school, start_date, end_date, current FROM alumni_education WHERE alumni_id IN ($placeholders) ORDER BY start_date DESC", $alumniIds, fn ($row) => $row + ['current' => (bool) $row['current']]);
        $experience = $this->groupedByAlumniId("SELECT alumni_id, experience_id, title, company, start_date, end_date, current, description, location_of_work, employment_status, employment_sector FROM alumni_experience WHERE alumni_id IN ($placeholders) ORDER BY start_date DESC", $alumniIds, fn ($row) => $row + ['current' => (bool) $row['current']]);

        $resume = [];
        $rows = $this->selectAll("SELECT alumni_id, resume_id, file_name, uploaded_at FROM alumni_resume WHERE alumni_id IN ($placeholders) ORDER BY uploaded_at DESC", $alumniIds);
        foreach ($rows as $row) {
            $alumniId = (int) $row['alumni_id'];
            if (!isset($resume[$alumniId])) {
                $resume[$alumniId] = $row;
            }
        }

        return ['skills' => $skills, 'education' => $education, 'experience' => $experience, 'resume' => $resume];
    }

    private function groupedByAlumniId(string $sql, array $ids, ?callable $mapRow = null): array
    {
        $out = [];
        foreach ($this->selectAll($sql, $ids) as $row) {
            $out[(int) $row['alumni_id']][] = $mapRow ? $mapRow($row) : $row;
        }

        return $out;
    }

    public function updateProfileFields(int $userId, array $data): bool
    {
        if (!empty($data['email'])) {
            $this->runUpdate('UPDATE user SET email=?, secondary_email=? WHERE user_id=?', [$data['email'], $data['secondary_email'], $userId]);
        }

        $campusId = isset($data['campus_id']) && $data['campus_id'] !== '' ? (int) $data['campus_id'] : null;

        $this->runUpdate('UPDATE alumni SET first_name=?, middle_name=?, last_name=?, birthdate=?, contact=?, gender=?, civil_status=?, city=?, province=?, year_graduated=?, college=?, course=?, campus_id=? WHERE user_id=?', [
            $data['first_name'], $data['middle_name'], $data['last_name'], $data['birthdate'], $data['contact'],
            $data['gender'], $data['civil_status'], $data['city'], $data['province'], $data['year_graduated'],
            $data['college'], $data['course'], $campusId, $userId,
        ]);

        return true;
    }

    public function addEducation(int $alumniId, array $data): int
    {
        $current = !empty($data['current']) && $data['current'] == '1' ? 1 : 0;
        // See updateEducation() below for why '' / the literal string "null"
        // must both become a real NULL for this column.
        $endDate = in_array($data['end_date'] ?? '', ['', 'null'], true) ? null : $data['end_date'];

        return $this->insertGetId('INSERT INTO alumni_education (alumni_id, degree, school, start_date, end_date, current) VALUES (?, ?, ?, ?, ?, ?)', [
            $alumniId, $data['degree'], $data['school'], $data['start_date'], $endDate, $current,
        ]);
    }

    public function updateEducation(int $educationId, int $alumniId, array $data): bool
    {
        $current = !empty($data['current']) && $data['current'] == '1' ? 1 : 0;
        // end_date is a nullable DATE column, but this DB's active strict
        // SQL mode rejects anything that isn't NULL or a real date. The
        // form can send '' (the disabled date input's value when "I
        // currently study here" is checked) or, when re-editing an entry
        // whose end_date was already null, the literal 4-character string
        // "null" — FormData.append() stringifies a JS null argument, it
        // doesn't send an actually-empty field.
        $endDate = in_array($data['end_date'] ?? '', ['', 'null'], true) ? null : $data['end_date'];

        // Verified by ownership, not by the UPDATE's affected-row count —
        // MySQL reports 0 affected rows whenever every column already held
        // the value being written (e.g. saving an entry without actually
        // changing anything), which would otherwise make a legitimate,
        // successful save look like "not found".
        if ($this->selectOne('SELECT education_id FROM alumni_education WHERE education_id = ? AND alumni_id = ?', [$educationId, $alumniId]) === null) {
            return false;
        }

        $this->runUpdate('UPDATE alumni_education SET degree=?, school=?, start_date=?, end_date=?, current=? WHERE education_id=? AND alumni_id=?', [
            $data['degree'], $data['school'], $data['start_date'], $endDate, $current, $educationId, $alumniId,
        ]);

        return true;
    }

    public function deleteEducation(int $educationId, int $alumniId): bool
    {
        return $this->runDelete('DELETE FROM alumni_education WHERE education_id = ? AND alumni_id = ?', [$educationId, $alumniId]) > 0;
    }

    public function addSkill(int $alumniId, string $name, string $certificateText, ?string $certificateFile): int
    {
        // certificate_file has no DB default and is NOT NULL — a
        // certificate upload is optional here (see ProfileController::
        // addSkill(), which only sets it when a file was actually
        // attached), so '' is this codebase's established "not provided"
        // placeholder for that case, same convention used for other
        // optional-at-creation VARCHAR columns.
        return $this->insertGetId('INSERT INTO alumni_skill (alumni_id, name, certificate, certificate_file) VALUES (?, ?, ?, ?)', [$alumniId, $name, $certificateText, $certificateFile ?? '']);
    }

    public function deleteSkill(int $skillId, int $alumniId): bool
    {
        return $this->runDelete('DELETE FROM alumni_skill WHERE skill_id = ? AND alumni_id = ?', [$skillId, $alumniId]) >= 0;
    }

    public function addCertification(int $alumniId, string $name, ?string $issuer, ?string $issueDate, ?string $certificateFile): int
    {
        return $this->insertGetId('INSERT INTO alumni_certification (alumni_id, name, issuer, issue_date, certificate_file) VALUES (?, ?, ?, ?, ?)', [$alumniId, $name, $issuer, $issueDate, $certificateFile]);
    }

    public function updateCertification(int $certificationId, int $alumniId, string $name, ?string $issuer, ?string $issueDate): bool
    {
        return $this->runUpdate('UPDATE alumni_certification SET name=?, issuer=?, issue_date=? WHERE certification_id=? AND alumni_id=?', [$name, $issuer, $issueDate, $certificationId, $alumniId]) >= 0;
    }

    public function updateCertificationFile(int $certificationId, int $alumniId, string $certificateFile): bool
    {
        return $this->runUpdate('UPDATE alumni_certification SET certificate_file=? WHERE certification_id=? AND alumni_id=?', [$certificateFile, $certificationId, $alumniId]) >= 0;
    }

    public function deleteCertification(int $certificationId, int $alumniId): bool
    {
        return $this->runDelete('DELETE FROM alumni_certification WHERE certification_id = ? AND alumni_id = ?', [$certificationId, $alumniId]) >= 0;
    }

    public function addExperience(int $alumniId, array $data): int
    {
        $current = !empty($data['current']) && $data['current'] == '1' ? 1 : 0;
        // Same reasoning as updateExperience() below: end_date must be NULL,
        // not '' or the stringified-null the disabled/unset date input can
        // send, or this DB's strict SQL mode rejects the insert.
        $endDate = $current ? null : (in_array($data['end_date'] ?? '', ['', 'null'], true) ? null : $data['end_date']);

        return $this->insertGetId('INSERT INTO alumni_experience (alumni_id, title, company, start_date, end_date, current, description, location_of_work, employment_status, employment_sector) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $alumniId, $data['title'], $data['company'], $data['start_date'], $endDate, $current,
            $data['description'], $data['location_of_work'], $data['employment_status'], $data['employment_sector'],
        ]);
    }

    public function updateExperience(int $experienceId, int $alumniId, array $data): bool
    {
        $current = !empty($data['current']) && $data['current'] == '1' ? 1 : 0;
        $endDate = $current || in_array($data['end_date'] ?? '', ['', 'null'], true) ? null : $data['end_date'];

        return $this->runUpdate('UPDATE alumni_experience SET title=?, company=?, start_date=?, end_date=?, current=?, description=?, location_of_work=?, employment_status=?, employment_sector=?, updated_at=NOW() WHERE experience_id=? AND alumni_id=?', [
            $data['title'], $data['company'], $data['start_date'], $endDate, $current,
            $data['description'], $data['location_of_work'], $data['employment_status'], $data['employment_sector'],
            $experienceId, $alumniId,
        ]) > 0;
    }

    public function deleteExperience(int $experienceId, int $alumniId): bool
    {
        return $this->runDelete('DELETE FROM alumni_experience WHERE experience_id = ? AND alumni_id = ?', [$experienceId, $alumniId]) > 0;
    }

    public function latestResumeRow(int $alumniId): ?array
    {
        return $this->selectOne('SELECT resume_id, file_name FROM alumni_resume WHERE alumni_id = ? ORDER BY uploaded_at DESC LIMIT 1', [$alumniId]);
    }

    public function addResume(int $alumniId, string $fileName): int
    {
        return $this->insertGetId('INSERT INTO alumni_resume (alumni_id, file_name, uploaded_at) VALUES (?, ?, NOW())', [$alumniId, $fileName]);
    }

    public function replaceResume(int $resumeId, string $fileName): bool
    {
        return $this->runUpdate('UPDATE alumni_resume SET file_name = ?, uploaded_at = NOW() WHERE resume_id = ?', [$fileName, $resumeId]) >= 0;
    }

    public function deleteResume(int $resumeId): bool
    {
        return $this->runDelete('DELETE FROM alumni_resume WHERE resume_id = ?', [$resumeId]) >= 0;
    }

    public function profilePicByUserId(int $userId): ?string
    {
        return $this->selectOne('SELECT profile_pic FROM alumni WHERE user_id = ? LIMIT 1', [$userId])['profile_pic'] ?? null;
    }

    public function updateProfilePicByUserId(int $userId, ?string $fileName): bool
    {
        return $this->runUpdate('UPDATE alumni SET profile_pic = ? WHERE user_id = ?', [$fileName, $userId]) >= 0;
    }

    public function updateVerificationDocumentByUserId(int $userId, string $fileName): bool
    {
        return $this->runUpdate('UPDATE alumni SET verification_document = ? WHERE user_id = ?', [$fileName, $userId]) >= 0;
    }

    public function verificationDocumentByUserId(int $userId): ?string
    {
        return $this->selectOne('SELECT verification_document FROM alumni WHERE user_id = ? LIMIT 1', [$userId])['verification_document'] ?? null;
    }

    public function register(int $userId, array $data, string $verificationDocument): void
    {
        $campusId = isset($data['campus_id']) && $data['campus_id'] !== '' ? (int) $data['campus_id'] : null;

        try {
            $alumniId = $this->insertGetId('INSERT INTO alumni (user_id, first_name, middle_name, last_name, birthdate, contact, gender, civil_status, city, province, year_graduated, college, course, campus_id, verification_document) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $userId, $data['first_name'], $data['middle_name'], $data['last_name'], $data['birthdate'],
                $data['contact'], $data['gender'], $data['civil_status'], $data['city'], $data['province'],
                $data['year_graduated'], $data['college'], $data['course'], $campusId, $verificationDocument,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Alumni registration failed: '.$e->getMessage());
        }

        // Signup already collects the alumni's course and campus — seed an
        // education entry from it so "Education" isn't empty until they
        // manually re-enter the exact same degree themselves.
        if ($campusId !== null) {
            $campus = (new Campus())->findById($campusId);
            if ($campus) {
                $this->addEducation($alumniId, [
                    'degree' => $data['course'],
                    'school' => "Laguna State Polytechnic University - {$campus['name']} Campus",
                    'start_date' => null,
                    'end_date' => null,
                    'current' => 0,
                ]);
            }
        }
    }

    public function allByStatus(string $status): array
    {
        return $this->selectAll('SELECT a.*, u.email, u.secondary_email, u.status
            FROM alumni a JOIN user u ON a.user_id = u.user_id
            WHERE u.status = ? ORDER BY a.alumni_id DESC', [$status]);
    }

    public function allByStatusForCampus(string $status, int $campusId): array
    {
        return $this->selectAll('SELECT a.*, u.email, u.secondary_email, u.status
            FROM alumni a JOIN user u ON a.user_id = u.user_id
            WHERE u.status = ? AND a.campus_id = ? ORDER BY a.alumni_id DESC', [$status, $campusId]);
    }

    /**
     * Paginated + optionally search-filtered variant of allByStatus()/
     * allByStatusForCampus(), for alumni lists too large to ship in one
     * response. $search matches name, email, course, and college — the
     * same fields the frontend's client-side search used to filter on.
     * Pass $campusId = null for the unscoped (superadmin) view.
     */
    public function allByStatusPaginated(string $status, ?int $campusId, int $limit, int $offset, string $search = ''): array
    {
        [$where, $params] = $this->statusSearchClause($status, $campusId, $search);
        $sql = 'SELECT a.*, u.email, u.secondary_email, u.status
            FROM alumni a JOIN user u ON a.user_id = u.user_id'
            .$where.
            ' ORDER BY a.alumni_id DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->selectAll($sql, $params);
    }

    public function countByStatus(string $status, ?int $campusId, string $search = ''): int
    {
        [$where, $params] = $this->statusSearchClause($status, $campusId, $search);
        $sql = 'SELECT COUNT(*) as total FROM alumni a JOIN user u ON a.user_id = u.user_id'.$where;

        return (int) ($this->selectOne($sql, $params)['total'] ?? 0);
    }

    private function statusSearchClause(string $status, ?int $campusId, string $search): array
    {
        $where = ' WHERE u.status = ?';
        $params = [$status];

        if ($campusId !== null) {
            $where .= ' AND a.campus_id = ?';
            $params[] = $campusId;
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $where .= ' AND (a.first_name LIKE ? OR a.last_name LIKE ? OR u.email LIKE ? OR a.course LIKE ? OR a.college LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [$where, $params];
    }

    public function findByAlumniId(int $alumniId): ?array
    {
        return $this->selectOne('SELECT a.*, u.email, u.secondary_email, u.status, u.user_id
            FROM alumni a JOIN user u ON a.user_id = u.user_id
            WHERE a.alumni_id = ? LIMIT 1', [$alumniId]);
    }

    public function approve(int $userId): bool
    {
        return $this->runUpdate("UPDATE user SET status = 'Active' WHERE user_id = ?", [$userId]) >= 0;
    }

    public function deleteByAlumniId(int $alumniId): bool
    {
        $row = $this->selectOne('SELECT user_id FROM alumni WHERE alumni_id = ?', [$alumniId]);
        if (!$row) {
            return false;
        }
        $userId = $row['user_id'];

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($alumniId, $userId) {
                $this->runDelete('DELETE FROM alumni WHERE alumni_id = ?', [$alumniId]);
                $this->runDelete('DELETE FROM user WHERE user_id = ?', [$userId]);
            });

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function updateProfile(int $alumniId, int $userId, array $userFields, array $alumniFields): bool
    {
        if (!empty($userFields)) {
            $set = implode(',', array_map(fn ($c) => "{$c} = ?", array_keys($userFields)));
            $this->runUpdate("UPDATE user SET {$set} WHERE user_id = ?", [...array_values($userFields), $userId]);
        }

        if (!empty($alumniFields)) {
            $set = implode(',', array_map(fn ($c) => "{$c} = ?", array_keys($alumniFields)));
            $this->runUpdate("UPDATE alumni SET {$set} WHERE alumni_id = ?", [...array_values($alumniFields), $alumniId]);
        }

        return true;
    }

    public function contactByUserId(int $userId): ?array
    {
        $row = $this->selectOne('SELECT u.email, u.secondary_email, a.first_name, a.last_name
            FROM alumni a
            JOIN user u ON a.user_id = u.user_id
            WHERE a.user_id = ? LIMIT 1', [$userId]);

        return $row;
    }
}
