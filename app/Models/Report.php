<?php

namespace App\Models;

use App\Concerns\LegacyQueries;
use Illuminate\Support\Facades\DB;

/** Ported from backend/Models/Report.php — see User.php's docblock for the porting approach. */
class Report
{
    use LegacyQueries;

    /**
     * Colleges where "Major in X" / "(X)" suffixes are true, meaningfully
     * distinct programs declared from year one — everywhere else, that kind
     * of suffix is a late-added (3rd-year) specialization label, so it gets
     * collapsed into the base program for reporting purposes.
     */
    private const KEEP_MAJOR_COLLEGES = [
        'College of Business Administration and Accountancy',
        'College of Industrial Technology',
        'College of Teacher Education',
    ];

    /** Reporting-grouping key for a course. */
    public static function normalizeProgram(?string $college, string $course): string
    {
        if ($college !== null && in_array(trim($college), self::KEEP_MAJOR_COLLEGES, true)) {
            return $course;
        }

        $normalized = preg_replace('/\s*\([^)]*\)\s*$/', '', $course);
        $normalized = preg_replace('/\s+Major in .+$/i', '', $normalized);

        return trim($normalized);
    }

    private ?int $campusId;
    private ?string $college;
    private ?int $yearGraduated;

    public function __construct(?int $campusId = null, ?string $college = null, ?int $yearGraduated = null)
    {
        $this->campusId = $campusId;
        $this->college = ($college !== null && $college !== '') ? $college : null;
        $this->yearGraduated = $yearGraduated;
    }

    private function campusClause(string $alias, bool $isFirstCondition = false): string
    {
        $conditions = [];
        if ($this->campusId !== null) {
            $conditions[] = "{$alias}.campus_id = ".((int) $this->campusId);
        }
        if ($this->college !== null) {
            $conditions[] = "{$alias}.college = ".DB::connection()->getPdo()->quote($this->college);
        }
        if ($this->yearGraduated !== null) {
            $conditions[] = "{$alias}.year_graduated = ".((int) $this->yearGraduated);
        }

        if (empty($conditions)) {
            return '';
        }

        $keyword = $isFirstCondition ? 'WHERE' : 'AND';

        return " {$keyword} ".implode(' AND ', $conditions).' ';
    }

    public function courseCollegeJobRows(): array
    {
        return $this->selectAll("SELECT a.alumni_id, a.course, a.college, e.title as job_title, e.employment_status, e.employment_sector, e.location_of_work
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1"
            .$this->campusClause('a', true)."
            ORDER BY a.course, a.college");
    }

    public function sectorStats(): array
    {
        return $this->selectAll("SELECT COALESCE(e.employment_sector, 'Not Specified') as employment_sector, COUNT(DISTINCT a.alumni_id) as count
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id
            WHERE e.employment_status IS NOT NULL AND e.employment_status != ''"
            .$this->campusClause('a')."
            GROUP BY COALESCE(e.employment_sector, 'Not Specified')
            ORDER BY count DESC");
    }

    public function locationStats(): array
    {
        return $this->selectAll("SELECT COALESCE(e.location_of_work, 'Not Specified') as location_of_work, COUNT(DISTINCT a.alumni_id) as count
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id
            WHERE e.employment_status IS NOT NULL AND e.employment_status != ''"
            .$this->campusClause('a')."
            GROUP BY COALESCE(e.location_of_work, 'Not Specified')
            ORDER BY count DESC");
    }

    public function employedCount(): int
    {
        $row = $this->selectOne("SELECT COUNT(DISTINCT a.alumni_id) as count
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id
            WHERE e.employment_status IS NOT NULL AND e.employment_status != ''"
            .$this->campusClause('a'));

        return (int) ($row['count'] ?? 0);
    }

    public function unemployedCount(): int
    {
        // Anti-join (matches DashboardStats' C3): alumni with no experience row
        // that carries a non-empty employment_status. MySQL drives this from an
        // index on alumni_experience(alumni_id) instead of materialising every
        // employed id for a NOT IN (SELECT ...).
        $row = $this->selectOne('SELECT COUNT(*) as count
            FROM alumni a
            LEFT JOIN alumni_experience e2
              ON e2.alumni_id = a.alumni_id
             AND e2.employment_status IS NOT NULL AND e2.employment_status <> \'\'
            WHERE e2.alumni_id IS NULL'
            .$this->campusClause('a'));

        return (int) ($row['count'] ?? 0);
    }

    /**
     * One aggregated row per (course, college): graduate count and
     * employed count. Replaces pulling one row per alumnus into PHP just
     * to tally these (ReportService::summary()).
     *
     * COUNT(*) over the `AND e.current = 1` LEFT JOIN mirrors the old
     * row-per-current-experience semantics exactly (an alumnus with two
     * current jobs counted twice; one with none counted once).
     */
    public function programEmploymentAggregate(): array
    {
        return $this->selectAll(
            "SELECT a.course, a.college,
                    COUNT(*) AS total_graduates,
                    SUM(CASE WHEN e.employment_status IS NOT NULL AND e.employment_status <> '' THEN 1 ELSE 0 END) AS employed_count
             FROM alumni a
             LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1"
            .$this->campusClause('a', true).'
             GROUP BY a.course, a.college'
        );
    }

    /**
     * Employed graduates with a job title, grouped by (course, college,
     * job title). The caller classifies each distinct title once (cached)
     * instead of once per alumnus — the "job match rate" input for
     * ReportService::summary().
     */
    public function employedJobTitleCounts(): array
    {
        return $this->selectAll(
            "SELECT a.course, a.college, e.title AS job_title, COUNT(*) AS cnt
             FROM alumni a
             JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1
             WHERE e.employment_status IS NOT NULL AND e.employment_status <> ''
               AND e.title IS NOT NULL AND e.title <> ''
               AND a.course IS NOT NULL AND a.course <> ''"
            .$this->campusClause('a').'
             GROUP BY a.course, a.college, e.title'
        );
    }

    /** Distinct (course, current job title) pairs in scope — to warm AlignmentService in one shot. */
    public function distinctCourseJobTitlePairs(): array
    {
        return $this->selectAll(
            "SELECT DISTINCT a.course, e.title AS job_title
             FROM alumni a
             JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1
             WHERE e.title IS NOT NULL AND e.title <> ''
               AND a.course IS NOT NULL AND a.course <> ''"
            .$this->campusClause('a')
        );
    }

    public function employmentSummaryRows(): array
    {
        return $this->selectAll('SELECT a.course, a.college, e.title as job_title, e.employment_status
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1'
            .$this->campusClause('a', true).'
            ORDER BY a.college, a.course');
    }

    public function sectorSummaryRows(): array
    {
        return $this->selectAll("SELECT a.course, a.college, e.employment_sector, COUNT(*) as count
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1
            WHERE (e.employment_status = 'Employed' OR e.employment_status = 'Probational' OR e.employment_status = 'Regular')"
            .$this->campusClause('a')."
            GROUP BY a.course, a.college, e.employment_sector
            ORDER BY a.college, a.course, e.employment_sector");
    }

    public function locationSummaryRows(): array
    {
        return $this->selectAll("SELECT a.course, a.college, e.location_of_work, COUNT(*) as count
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1
            WHERE (e.employment_status = 'Employed' OR e.employment_status = 'Probational' OR e.employment_status = 'Regular')"
            .$this->campusClause('a')."
            GROUP BY a.course, a.college, e.location_of_work
            ORDER BY a.college, a.course, e.location_of_work");
    }

    public function statusSummaryRows(): array
    {
        return $this->selectAll("SELECT a.course, a.college, e.employment_status, COUNT(*) as count
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1
            WHERE (e.employment_status = 'Employed' OR e.employment_status = 'Probational' OR e.employment_status = 'Regular' OR e.employment_status = 'Contractual')"
            .$this->campusClause('a')."
            GROUP BY a.course, a.college, e.employment_status
            ORDER BY a.college, a.course, e.employment_status");
    }

    /** Distinct college names available for the current campus scope (ignores the college filter itself). */
    public function distinctColleges(): array
    {
        $sql = "SELECT DISTINCT college FROM alumni a WHERE college IS NOT NULL AND college != ''";
        if ($this->campusId !== null) {
            $sql .= ' AND a.campus_id = '.((int) $this->campusId);
        }
        $sql .= ' ORDER BY college';

        return array_map(static fn ($row) => $row['college'], $this->selectAll($sql));
    }

    /** Distinct graduation years available for the current campus scope (ignores the year filter itself). */
    public function distinctYears(): array
    {
        $sql = "SELECT DISTINCT year_graduated FROM alumni a WHERE year_graduated IS NOT NULL AND year_graduated != ''";
        if ($this->campusId !== null) {
            $sql .= ' AND a.campus_id = '.((int) $this->campusId);
        }
        $sql .= ' ORDER BY year_graduated DESC';

        return array_map(static fn ($row) => (int) $row['year_graduated'], $this->selectAll($sql));
    }

    public function industryDeterminationRows(): array
    {
        return $this->selectAll("SELECT a.course, a.college, a.gender, e.title as job_title, e.company
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1
            WHERE e.title IS NOT NULL AND e.title != ''"
            .$this->campusClause('a')."
            ORDER BY a.college, a.course, a.gender");
    }

    public function detailedEmploymentRows(): array
    {
        return $this->selectAll("SELECT a.alumni_id, a.user_id, a.first_name, a.middle_name, a.last_name, a.birthdate, a.contact,
                   a.gender, a.civil_status, a.city, a.province, a.year_graduated, a.college, a.course,
                   a.verification_document, a.created_at, a.profile_pic,
                   e.experience_id, e.title, e.company, e.start_date, e.end_date, e.current, e.description,
                   e.location_of_work, e.employment_status, e.employment_sector,
                   e.created_at as exp_created_at, e.updated_at as exp_updated_at, u.email
            FROM alumni a
            LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current = 1
            LEFT JOIN user u ON a.user_id = u.user_id"
            .$this->campusClause('a', true)."
            ORDER BY a.college, a.course, a.last_name, a.first_name");
    }

    public function industryAnalysisRows(): array
    {
        return $this->selectAll("SELECT a.alumni_id, a.course, a.gender, e.title as job_title, e.company, e.description as job_description, e.employment_sector
            FROM alumni a
            JOIN alumni_experience e ON a.alumni_id = e.alumni_id
            WHERE (e.employment_status = 'Employed' OR e.employment_status = 'Probational' OR e.employment_status = 'Regular')
            AND e.current = 1"
            .$this->campusClause('a')."
            ORDER BY a.course, a.gender");
    }
}
