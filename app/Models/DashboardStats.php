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

    /**
     * C2: one grouped query instead of loading every alumnus + every
     * "employed" id into PHP. Output is unchanged:
     *   [abbrevCollege => ['graduates' => int, 'employed' => int]].
     *
     * "employed" keeps the original meaning: a distinct alumnus who has at
     * least one experience row that is current, or has no end date, or
     * whose end date is today or later.
     */
    private function graduatesPerCollege(): array
    {
        $sql = 'SELECT a.college,
                       COUNT(DISTINCT a.alumni_id) AS graduates,
                       COUNT(DISTINCT CASE
                             WHEN e.current = 1 OR e.end_date IS NULL OR e.end_date >= CURDATE()
                             THEN e.alumni_id END) AS employed
                FROM alumni a
                LEFT JOIN alumni_experience e ON e.alumni_id = a.alumni_id'
                .$this->campusClause('a', true).'
                GROUP BY a.college';

        $colleges = [];
        foreach ($this->selectAll($sql) as $row) {
            $colleges[$this->abbreviateCollege($row['college'])] = [
                'graduates' => (int) $row['graduates'],
                'employed' => (int) $row['employed'],
            ];
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

        // C3: anti-join instead of `NOT IN (SELECT ...)`. Same set — alumni
        // with no experience row that is current / open-ended / not-yet-ended —
        // but MySQL can drive it from an index instead of materialising every
        // "employed" id. COUNT(*) here is one row per non-matching alumnus
        // (the LEFT JOIN yields exactly one NULL row for those), so it stays
        // a straight alumni count.
        $sql = 'SELECT a.course, a.college, COUNT(*) as cnt
                                   FROM alumni a
                                   LEFT JOIN alumni_experience e
                                     ON e.alumni_id = a.alumni_id
                                    AND (e.current = 1 OR e.end_date IS NULL OR e.end_date >= CURDATE())
                                   WHERE e.alumni_id IS NULL'
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

    /**
     * C4: campus-grouped variant of employmentStatusPerProgram(). Computes
     * the per-program employment-status breakdown for MANY campuses in 3
     * queries total (was 3 queries PER campus in a loop). Returns
     *   [campusId => [program => [status => count]]].
     *
     * Only the given campus ids are queried, so a scoped admin can never
     * pull another campus's numbers.
     *
     * @param  int[]  $campusIds
     */
    public function employmentStatusPerProgramByCampus(array $campusIds): array
    {
        $campusIds = array_values(array_unique(array_map('intval', $campusIds)));
        if (empty($campusIds)) {
            return [];
        }

        $statusLabels = ['Probational', 'Contractual', 'Regular', 'Self-employed', 'Unemployed'];
        $in = implode(',', $campusIds);
        $currentDate = date('Y-m-d');

        // 1. Program keys per campus (so programs with zero employment still show).
        $out = [];
        foreach ($this->selectAll(
            "SELECT a.campus_id, a.course, a.college
             FROM alumni a
             WHERE a.campus_id IN ({$in})
             GROUP BY a.campus_id, a.course, a.college"
        ) as $row) {
            $cid = (int) $row['campus_id'];
            $program = $this->abbreviateCourse(Report::normalizeProgram($row['college'], $row['course']));
            if (!isset($out[$cid][$program])) {
                $out[$cid][$program] = array_fill_keys($statusLabels, 0);
            }
        }

        // 2. Active-experience status counts per campus/program.
        foreach ($this->selectAll(
            "SELECT a.campus_id, a.course, a.college, e.employment_status, COUNT(DISTINCT a.alumni_id) AS cnt
             FROM alumni a
             JOIN alumni_experience e ON e.alumni_id = a.alumni_id
             WHERE (e.current = 1 OR e.end_date IS NULL OR e.end_date >= ?)
               AND a.campus_id IN ({$in})
             GROUP BY a.campus_id, a.course, a.college, e.employment_status",
            [$currentDate]
        ) as $row) {
            $cid = (int) $row['campus_id'];
            $program = $this->abbreviateCourse(Report::normalizeProgram($row['college'], $row['course']));
            $status = $row['employment_status'];
            if (isset($out[$cid][$program][$status])) {
                $out[$cid][$program][$status] += (int) $row['cnt'];
            }
        }

        // 3. Unemployed (no active experience) per campus/program — anti-join.
        foreach ($this->selectAll(
            "SELECT a.campus_id, a.course, a.college, COUNT(*) AS cnt
             FROM alumni a
             LEFT JOIN alumni_experience e
               ON e.alumni_id = a.alumni_id
              AND (e.current = 1 OR e.end_date IS NULL OR e.end_date >= CURDATE())
             WHERE e.alumni_id IS NULL
               AND a.campus_id IN ({$in})
             GROUP BY a.campus_id, a.course, a.college"
        ) as $row) {
            $cid = (int) $row['campus_id'];
            $program = $this->abbreviateCourse(Report::normalizeProgram($row['college'], $row['course']));
            if (isset($out[$cid][$program])) {
                $out[$cid][$program]['Unemployed'] += (int) $row['cnt'];
            }
        }

        return $out;
    }

    /**
     * C4: distinct colleges for many campuses in one query.
     *
     * @param  int[]  $campusIds
     * @return array<int, string[]>  campusId => sorted college names
     */
    public function collegesByCampus(array $campusIds): array
    {
        $campusIds = array_values(array_unique(array_map('intval', $campusIds)));
        if (empty($campusIds)) {
            return [];
        }
        $in = implode(',', $campusIds);

        $out = [];
        foreach ($this->selectAll(
            "SELECT DISTINCT campus_id, college
             FROM alumni
             WHERE campus_id IN ({$in}) AND college IS NOT NULL AND college <> ''
             ORDER BY college"
        ) as $row) {
            $out[(int) $row['campus_id']][] = $row['college'];
        }

        return $out;
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

    /**
     * C1: location CLUSTERS, not every alumnus. One grouped query; the
     * result is bounded by (distinct locations x distinct courses), not by
     * the alumni count, so it stays small at 100k+ records. The per-location
     * alumni list is fetched lazily by alumniAtLocation() when a marker
     * popup is opened.
     *
     * @return array<string, array{city:string, province:string, count:int, employed:int, top_courses: array<int, array{course:string, count:int}>}>
     */
    public function alumniMap(): array
    {
        $sql = "SELECT a.city, a.province, a.course,
                       COUNT(DISTINCT a.alumni_id) AS n,
                       COUNT(DISTINCT CASE
                             WHEN e.current = 1 OR e.end_date IS NULL OR e.end_date >= CURDATE()
                             THEN e.alumni_id END) AS employed
                FROM alumni a
                LEFT JOIN alumni_experience e ON e.alumni_id = a.alumni_id
                WHERE a.city IS NOT NULL AND a.city <> '' AND a.province IS NOT NULL AND a.province <> ''"
                .$this->campusClause('a').'
                GROUP BY a.city, a.province, a.course';

        $clusters = [];
        foreach ($this->selectAll($sql) as $row) {
            $key = $row['city'].', '.$row['province'];
            if (!isset($clusters[$key])) {
                $clusters[$key] = [
                    'city' => $row['city'],
                    'province' => $row['province'],
                    'count' => 0,
                    'employed' => 0,
                    '_courses' => [],
                ];
            }
            $clusters[$key]['count'] += (int) $row['n'];
            $clusters[$key]['employed'] += (int) $row['employed'];
            if ($row['course'] !== null && $row['course'] !== '') {
                $clusters[$key]['_courses'][$row['course']] = (int) $row['n'];
            }
        }

        foreach ($clusters as &$c) {
            arsort($c['_courses']);
            $top = [];
            foreach (array_slice($c['_courses'], 0, 3, true) as $course => $n) {
                $top[] = ['course' => $course, 'count' => $n];
            }
            $c['top_courses'] = $top;
            unset($c['_courses']);
        }
        unset($c);

        return $clusters;
    }

    /**
     * C1: paginated alumni for one location, loaded on demand when a map
     * marker popup is opened. Only the fields the popup renders, one page
     * at a time — never the whole location.
     *
     * @return array{total:int, page:int, per_page:int, alumni: array<int, array<string, mixed>>}
     */
    public function alumniAtLocation(string $city, string $province, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $total = (int) ($this->selectOne(
            'SELECT COUNT(*) AS c FROM alumni a WHERE a.city = ? AND a.province = ?'.$this->campusClause('a'),
            [$city, $province]
        )['c'] ?? 0);

        $rows = $this->selectAll(
            'SELECT a.alumni_id, a.first_name, a.middle_name, a.last_name, a.profile_pic,
                    a.course, a.college, a.year_graduated
             FROM alumni a
             WHERE a.city = ? AND a.province = ?'.$this->campusClause('a').'
             ORDER BY a.last_name, a.first_name
             LIMIT '.$limit.' OFFSET '.$offset,
            [$city, $province]
        );

        $alumni = [];
        if (!empty($rows)) {
            $ids = array_map(static fn ($r) => (int) $r['alumni_id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $latest = [];
            foreach ($this->selectAll(
                "SELECT alumni_id, title, company, start_date, end_date, description, employment_status, location_of_work
                 FROM alumni_experience
                 WHERE alumni_id IN ({$placeholders})
                   AND (current = 1 OR end_date IS NULL OR end_date >= CURDATE())
                 ORDER BY alumni_id, start_date DESC",
                $ids
            ) as $row) {
                $aid = (int) $row['alumni_id'];
                if (!isset($latest[$aid])) {
                    $latest[$aid] = $row;
                }
            }

            foreach ($rows as $row) {
                $aid = (int) $row['alumni_id'];
                $exp = $latest[$aid] ?? null;
                $alumni[] = [
                    'name' => trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']),
                    'profile_pic' => $row['profile_pic'] ? 'uploads/profile_picture/'.$row['profile_pic'] : null,
                    'course' => $row['course'],
                    'college' => $row['college'],
                    'year_graduated' => $row['year_graduated'],
                    'status' => $exp ? 'Employed' : 'Unemployed',
                    'work_details' => $exp ? [
                        'title' => $exp['title'],
                        'company' => $exp['company'],
                        'start_date' => $exp['start_date'],
                        'end_date' => $exp['end_date'],
                        'description' => $exp['description'],
                        'employment_status' => $exp['employment_status'],
                        'location_of_work' => $exp['location_of_work'],
                    ] : null,
                ];
            }
        }

        return [
            'total' => $total,
            'page' => (int) floor($offset / $limit) + 1,
            'per_page' => $limit,
            'alumni' => $alumni,
        ];
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
