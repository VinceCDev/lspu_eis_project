<?php

namespace App\Models;

use App\Concerns\LegacyQueries;
use Illuminate\Support\Facades\DB;

/** Ported from backend/Models/DashboardStats.php — see User.php's docblock for the porting approach. */
class DashboardStats
{
    use LegacyQueries;

    private ?int $campusId;
    private ?string $college;

    public function __construct(?int $campusId = null, ?string $college = null)
    {
        $this->campusId = $campusId;
        $this->college = ($college !== null && $college !== '') ? $college : null;
    }

    /** WHERE/AND clause scoping a query to the current campus and/or college, or '' when neither is set. */
    private function campusClause(string $alias, bool $isFirstCondition = false): string
    {
        $conditions = [];
        if ($this->campusId !== null) {
            $conditions[] = "{$alias}.campus_id = ".((int) $this->campusId);
        }
        if ($this->college !== null) {
            $conditions[] = "{$alias}.college = ".DB::connection()->getPdo()->quote($this->college);
        }

        if (empty($conditions)) {
            return '';
        }

        $keyword = $isFirstCondition ? 'WHERE' : 'AND';

        return " {$keyword} ".implode(' AND ', $conditions).' ';
    }

    private const COLLEGE_ABBREVIATIONS = [
        'College of Computer Studies' => 'CCS',
        'College of Business Administration and Accountancy' => 'CBAA',
        'College of Arts and Sciences' => 'CAS',
        'College of Teacher Education' => 'CTE',
        'College of Engineering' => 'COE',
        'College of Agriculture' => 'CA',
        'College of Criminal Justice Education' => 'CCJE',
        'College of Industrial Technology' => 'CIT',
        'College of International Hospitality and Tourism Management' => 'CIHTM',
        'College of Nursing and Allied Health' => 'CNAH',
        'College of Fisheries' => 'CF',
        'College of Food Nutrition and Dietetics' => 'CFND',
    ];

    private const COURSE_ABBREVIATIONS = [
        'BS Biology' => 'BS Bio',
        'BS Psychology' => 'BS Psych',
        'BS Office Administration' => 'BS OA',
        'BS Business Administration Major in Financial Management' => 'BSBA-FM',
        'BS Business Administration Major in Marketing Management' => 'BSBA-MM',
        'BS Accountancy' => 'BSA',
        'BS Information Technology' => 'BSIT',
        'BS Computer Science' => 'BSCS',
        'BS Criminology' => 'BSCrim',
        'BS Electronics Engineering' => 'BS ECE',
        'BS Electrical Engineering' => 'BS EE',
        'BS Computer Engineering' => 'BS CoE',
        'BS Hospitality Management' => 'BSHM',
        'BS Tourism Management' => 'BSTM',
        'BS Industrial Technology Major in Automotive Technology' => 'BSIT-AT',
        'BS Industrial Technology Major in Architectural Drafting' => 'BSIT-AD',
        'BS Industrial Technology Major in Electrical Technology' => 'BSIT-ET',
        'BS Industrial Technology Major in Electronics Technology' => 'BSIT-ELXT',
        'BS Industrial Technology Major in Food & Beverage Preparation and Service Management Technology' => 'BSIT-FBPSMT',
        'BS Industrial Technology Major in Heating, Ventilating, Air-Conditioning & Refrigeration Technology' => 'BSIT-HVACRT',
        'BS Elementary Education' => 'BEED',
        'BS Physical Education' => 'BPE',
        'BS Secondary Education Major in English' => 'BSED-English',
        'BS Secondary Education Major in Filipino' => 'BSED-Filipino',
        'BS Secondary Education Major in Mathematics' => 'BSED-Math',
        'BS Secondary Education Major in Science' => 'BSED-Science',
        'BS Secondary Education Major in Social Studies' => 'BSED-Social Studies',
        'BS Technology and Livelihood Education Major in Home Economics' => 'BS TLE-HE',
        'BS Technical-Vocational Teacher Education Major in Electrical Technology' => 'BS TVTED-ET',
        'BS Technical-Vocational Teacher Education Major in Electronics Technology' => 'BS TVTED-ELXT',
        'BS Technical-Vocational Teacher Education Major in Food & Service Management' => 'BS TVTED-FSM',
        'BS Technical-Vocational Teacher Education Major in Garments, Fashion & Design' => 'BS TVTED-GFD',
    ];

    private function abbreviateCollege(string $name): string
    {
        return self::COLLEGE_ABBREVIATIONS[$name] ?? $name;
    }

    private function abbreviateCourse(string $name): string
    {
        if (isset(self::COURSE_ABBREVIATIONS[$name])) {
            return self::COURSE_ABBREVIATIONS[$name];
        }

        $base = $name;
        $suffix = '';
        if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', $name, $m)) {
            $base = trim($m[1]);
            $suffix = preg_replace('/^Major in\s+/i', '', trim($m[2]));
        } elseif (preg_match('/^(.*?)\s+Major in\s+(.+)$/i', $name, $m)) {
            $base = trim($m[1]);
            $suffix = trim($m[2]);
        }

        $abbr = $this->acronymize($base);
        if ($suffix !== '') {
            $abbr .= '-'.$this->acronymize($suffix);
        }

        return $abbr;
    }

    private function acronymize(string $text): string
    {
        $words = preg_split('/[\s,]+/', trim($text));
        if (empty($words)) {
            return $text;
        }

        $degreePrefixes = ['BS', 'BA', 'AB', 'MS', 'MA', 'PhD', 'EdD'];
        $prefix = '';
        if (in_array($words[0], $degreePrefixes, true)) {
            $prefix = array_shift($words);
        }

        $skip = ['of', 'and', 'the', 'in', 'for'];
        $letters = [];
        foreach ($words as $word) {
            $clean = trim($word, '.,-');
            if ($clean === '' || in_array(mb_strtolower($clean), $skip, true)) {
                continue;
            }
            $letters[] = mb_strtoupper(mb_substr($clean, 0, 1));
        }

        $acronym = implode('', $letters);
        if ($acronym === '') {
            return $prefix !== '' ? $prefix : $text;
        }

        return $prefix.$acronym;
    }

    private function graduatesPerCollege(): array
    {
        $colleges = [];
        foreach ($this->selectAll('SELECT college, COUNT(*) as graduates FROM alumni'.$this->campusClause('alumni', true).' GROUP BY college') as $row) {
            $colleges[$this->abbreviateCollege($row['college'])] = [
                'graduates' => (int) $row['graduates'],
                'employed' => 0,
            ];
        }

        $employedIds = [];
        foreach ($this->selectAll('SELECT DISTINCT alumni_id FROM alumni_experience WHERE current = 1 OR (end_date IS NULL OR end_date >= CURDATE())') as $row) {
            $employedIds[(int) $row['alumni_id']] = true;
        }

        foreach ($this->selectAll('SELECT alumni_id, college FROM alumni'.$this->campusClause('alumni', true)) as $row) {
            $college = $this->abbreviateCollege($row['college']);
            if (isset($employedIds[(int) $row['alumni_id']])) {
                ++$colleges[$college]['employed'];
            }
        }

        return $colleges;
    }

    /** Groups by the same college-aware normalized program name as Reports (Report::normalizeProgram()). */
    public function employmentStatusPerProgram(): array
    {
        $statusLabels = ['Probational', 'Contractual', 'Regular', 'Self-employed', 'Unemployed'];
        $programs = [];

        foreach ($this->selectAll('SELECT course, college FROM alumni'.$this->campusClause('alumni', true).' GROUP BY course, college') as $row) {
            $program = $this->abbreviateCourse(Report::normalizeProgram($row['college'], $row['course']));
            if (!isset($programs[$program])) {
                $programs[$program] = array_fill_keys($statusLabels, 0);
            }
        }

        $currentDate = date('Y-m-d');
        $sql = "SELECT a.course, a.college, e.employment_status, COUNT(DISTINCT a.alumni_id) as cnt
                                   FROM alumni a
                                   JOIN alumni_experience e ON a.alumni_id = e.alumni_id
                                   WHERE (e.current = 1 OR (e.end_date IS NULL OR e.end_date >= ?))"
                                   .$this->campusClause('a')."
                                   GROUP BY a.course, a.college, e.employment_status";
        foreach ($this->selectAll($sql, [$currentDate]) as $row) {
            $program = $this->abbreviateCourse(Report::normalizeProgram($row['college'], $row['course']));
            $status = $row['employment_status'];
            if (isset($programs[$program][$status])) {
                $programs[$program][$status] += (int) $row['cnt'];
            }
        }

        $sql = 'SELECT a.course, a.college, COUNT(*) as cnt
                                   FROM alumni a
                                   WHERE a.alumni_id NOT IN (
                                       SELECT DISTINCT alumni_id
                                       FROM alumni_experience
                                       WHERE current = 1 OR (end_date IS NULL OR end_date >= CURDATE())
                                   )'
                                   .$this->campusClause('a')
                                   .' GROUP BY a.course, a.college';
        foreach ($this->selectAll($sql) as $row) {
            $program = $this->abbreviateCourse(Report::normalizeProgram($row['college'], $row['course']));
            if (isset($programs[$program])) {
                $programs[$program]['Unemployed'] += (int) $row['cnt'];
            }
        }

        return $programs;
    }

    private function workLocationDistribution(): array
    {
        $dist = ['Local' => 0, 'Abroad' => 0];
        $sql = 'SELECT location_of_work, COUNT(DISTINCT alumni_experience.alumni_id) as cnt
                                   FROM alumni_experience'
                                   .($this->campusId !== null ? ' JOIN alumni a ON a.alumni_id = alumni_experience.alumni_id' : '').'
                                   WHERE (current = 1 OR (end_date IS NULL OR end_date >= CURDATE()))'
                                   .$this->campusClause('a').'
                                   GROUP BY location_of_work';
        foreach ($this->selectAll($sql) as $row) {
            if (isset($dist[$row['location_of_work']])) {
                $dist[$row['location_of_work']] = (int) $row['cnt'];
            }
        }

        return $dist;
    }

    private function employmentSectorDistribution(): array
    {
        $dist = ['Government' => 0, 'Private' => 0];
        $sql = 'SELECT employment_sector, COUNT(DISTINCT alumni_experience.alumni_id) as cnt
                                   FROM alumni_experience'
                                   .($this->campusId !== null ? ' JOIN alumni a ON a.alumni_id = alumni_experience.alumni_id' : '').'
                                   WHERE (current = 1 OR (end_date IS NULL OR end_date >= CURDATE()))'
                                   .$this->campusClause('a').'
                                   GROUP BY employment_sector';
        foreach ($this->selectAll($sql) as $row) {
            if (isset($dist[$row['employment_sector']])) {
                $dist[$row['employment_sector']] = (int) $row['cnt'];
            }
        }

        return $dist;
    }

    private function coursesPerLocation(): array
    {
        $courses = [];
        foreach ($this->selectAll('SELECT location_of_work, a.course, COUNT(DISTINCT a.alumni_id) as graduates FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE e.current = 1'.$this->campusClause('a').' GROUP BY location_of_work, a.course') as $row) {
            $loc = $row['location_of_work'] ?: '';
            $courses[$loc][$row['course']] = ['graduates' => (int) $row['graduates'], 'employed' => 0];
        }

        foreach ($this->selectAll('SELECT location_of_work, a.course, COUNT(DISTINCT a.alumni_id) as employed FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE e.current = 1'.$this->campusClause('a').' GROUP BY location_of_work, a.course') as $row) {
            $loc = $row['location_of_work'] ?: '';
            if (isset($courses[$loc][$row['course']])) {
                $courses[$loc][$row['course']]['employed'] = (int) $row['employed'];
            }
        }

        return $courses;
    }

    private function coursesPerSector(): array
    {
        $courses = [];
        foreach ($this->selectAll('SELECT employment_sector, a.course, COUNT(DISTINCT a.alumni_id) as graduates FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE e.current = 1'.$this->campusClause('a').' GROUP BY employment_sector, a.course') as $row) {
            $sec = $row['employment_sector'] ?: '';
            $courses[$sec][$row['course']] = ['graduates' => (int) $row['graduates'], 'employed' => 0];
        }

        foreach ($this->selectAll('SELECT employment_sector, a.course, COUNT(DISTINCT a.alumni_id) as employed FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE e.current = 1'.$this->campusClause('a').' GROUP BY employment_sector, a.course') as $row) {
            $sec = $row['employment_sector'] ?: '';
            if (isset($courses[$sec][$row['course']])) {
                $courses[$sec][$row['course']]['employed'] = (int) $row['employed'];
            }
        }

        return $courses;
    }

    /** Raw (course, job title) pairs for currently-employed alumni; alignment classification happens in the Service layer. */
    public function currentCourseJobTitles(): array
    {
        return $this->selectAll('SELECT a.course, e.title FROM alumni a JOIN alumni_experience e ON a.alumni_id = e.alumni_id WHERE e.current = 1'.$this->campusClause('a'));
    }

    /**
     * Was 8 separate COUNT(*) queries (one per total + one per "yesterday"
     * count, each re-scanning the same table). Combined into 4 — one per
     * table — using conditional aggregation so each table is scanned once.
     * Also switched the "yesterday" filters from `DATE(col) = ...` to a
     * `col >= ? AND col < ?` range: the DATE() wrapper made the column's
     * index unusable, so every dashboard load was a full table scan on
     * exactly the columns the new alumni/jobs created_at indexes target.
     * Same metrics, same keys, same values — just fewer round trips.
     */
    public function totalsForCards(): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $jobsToday = date('Y-m-d');
        $jobsYesterday = date('Y-m-d', strtotime('-1 day'));

        $companies = $this->selectOne(
            'SELECT COUNT(*) as total, SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as yesterday FROM employer',
            [$yesterdayStart, $todayStart]
        );

        $alumniSql = 'SELECT COUNT(*) as total, SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as yesterday FROM alumni'
            .$this->campusClause('alumni', true);
        $alumni = $this->selectOne($alumniSql, [$yesterdayStart, $todayStart]);

        $jobs = $this->selectOne(
            'SELECT COUNT(*) as total, SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as yesterday FROM jobs',
            [$jobsYesterday, $jobsToday]
        );

        $applicationsSql = 'SELECT COUNT(*) as total, SUM(CASE WHEN app.applied_at >= ? AND app.applied_at < ? THEN 1 ELSE 0 END) as yesterday
            FROM applications app JOIN alumni a ON app.alumni_id = a.alumni_id'.$this->campusClause('a', true);
        $applications = $this->selectOne($applicationsSql, [$yesterdayStart, $todayStart]);

        return [
            'total_companies' => (int) ($companies['total'] ?? 0),
            'companies_yesterday' => (int) ($companies['yesterday'] ?? 0),
            'total_alumni' => (int) ($alumni['total'] ?? 0),
            'alumni_yesterday' => (int) ($alumni['yesterday'] ?? 0),
            'total_jobs' => (int) ($jobs['total'] ?? 0),
            'jobs_yesterday' => (int) ($jobs['yesterday'] ?? 0),
            'total_applications' => (int) ($applications['total'] ?? 0),
            'applications_yesterday' => (int) ($applications['yesterday'] ?? 0),
        ];
    }

    public function alumniMap(): array
    {
        $map = [];
        $rows = $this->selectAll("SELECT a.alumni_id, a.first_name, a.middle_name, a.last_name, a.profile_pic, a.course, a.year_graduated, a.city, a.province, a.college FROM alumni a WHERE a.city IS NOT NULL AND a.city != '' AND a.province IS NOT NULL AND a.province != ''".$this->campusClause('a'));

        $latestExperience = [];
        foreach ($this->selectAll("SELECT alumni_id, title, company, start_date, end_date, description, employment_status, location_of_work
            FROM alumni_experience
            WHERE current = 1 OR (end_date IS NULL OR end_date >= CURDATE())
            ORDER BY alumni_id, start_date DESC") as $row) {
            $alumniId = (int) $row['alumni_id'];
            if (!isset($latestExperience[$alumniId])) {
                $latestExperience[$alumniId] = $row;
            }
        }

        foreach ($rows as $row) {
            $locationKey = $row['city'].', '.$row['province'];
            $alumniId = (int) $row['alumni_id'];
            $expRow = $latestExperience[$alumniId] ?? null;

            $work = '';
            $workDetails = null;
            $employmentStatus = 'Unemployed';

            if ($expRow) {
                $work = $expRow['title'].' at '.$expRow['company'];
                $workDetails = [
                    'title' => $expRow['title'],
                    'company' => $expRow['company'],
                    'start_date' => $expRow['start_date'],
                    'end_date' => $expRow['end_date'],
                    'description' => $expRow['description'],
                    'employment_status' => $expRow['employment_status'],
                    'location_of_work' => $expRow['location_of_work'],
                ];
                $employmentStatus = 'Employed';
            }

            $map[$locationKey][] = [
                'name' => trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']),
                'profile_pic' => $row['profile_pic'] ? 'uploads/profile_picture/'.$row['profile_pic'] : null,
                'course' => $row['course'],
                'college' => $row['college'],
                'year_graduated' => $row['year_graduated'],
                'work' => $work,
                'work_details' => $workDetails,
                'status' => $employmentStatus,
            ];
        }

        return $map;
    }

    public function chartBreakdowns(): array
    {
        return [
            'graduates_per_college' => $this->graduatesPerCollege(),
            'employment_status_per_program' => $this->employmentStatusPerProgram(),
            'work_location_distribution' => $this->workLocationDistribution(),
            'employment_sector_distribution' => $this->employmentSectorDistribution(),
            'courses_per_location' => $this->coursesPerLocation(),
            'courses_per_sector' => $this->coursesPerSector(),
        ];
    }
}
