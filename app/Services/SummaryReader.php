<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reads the pre-aggregated rpt_* tables (see ReportingSummary) in exactly the row shapes the original live queries of
 * DashboardStats / Report returned, so the callers' PHP is unchanged and the two paths can be diffed for equality.
 *
 * Scope = campus (null = all) + college (null = any) + graduation year (null = any). Every query reads at most a few
 * thousand summary rows, however many alumni exist.
 */
final class SummaryReader
{
    public function __construct(
        private readonly ?int $campusId = null,
        private readonly ?string $college = null,
        private readonly ?int $year = null,
    ) {
    }

    /** @return array{0:string,1:array} WHERE fragment ("" or " AND ...") + bindings. $year=false ignores the year filter. */
    private function scope(bool $withCollege = true, bool $withYear = true): array
    {
        $sql = '';
        $bind = [];
        if ($this->campusId !== null) {
            $sql .= ' AND campus_id = ?';
            $bind[] = $this->campusId;
        }
        if ($withCollege && $this->college !== null) {
            $sql .= ' AND college = ?';
            $bind[] = $this->college;
        }
        if ($withYear && $this->year !== null) {
            $sql .= ' AND year_graduated = ?';
            $bind[] = $this->year;
        }

        return [$sql, $bind];
    }

    /** @return array<int, array<string,mixed>> */
    private function all(string $sql, array $bind = []): array
    {
        return array_map(static fn ($r) => (array) $r, DB::select($sql, $bind));
    }

    private function metric(string $name, string $dimAlias, string $extraSelect = '', string $extraGroup = ''): array
    {
        [$w, $b] = $this->scope();

        return $this->all(
            "SELECT dim_value AS {$dimAlias}{$extraSelect}, SUM(alumni_count) AS cnt FROM rpt_metric WHERE metric = ?{$w} GROUP BY dim_value{$extraGroup}",
            [$name, ...$b]
        );
    }

    /* ---------------------------- Reports ---------------------------- */

    public function alumniCount(): int
    {
        [$w, $b] = $this->scope();

        return (int) (DB::selectOne("SELECT COALESCE(SUM(alumni_count), 0) AS c FROM rpt_program WHERE 1=1{$w}", $b)->c ?? 0);
    }

    public function programEmploymentAggregate(): array
    {
        [$w, $b] = $this->scope();

        return $this->all(
            "SELECT course, college, SUM(current_rows) AS total_graduates, SUM(employed_current_rows) AS employed_count
             FROM rpt_program WHERE 1=1{$w} GROUP BY course, college",
            $b
        );
    }

    public function employedJobTitleCounts(): array
    {
        [$w, $b] = $this->scope();
        // no year filter: the year-less table (far fewer rows); with a year: the per-year table
        $table = $this->year === null ? 'rpt_title_all' : 'rpt_title';

        return $this->all(
            "SELECT course, college, title AS job_title, SUM(cnt_employed) AS cnt
             FROM {$table} WHERE 1=1{$w} GROUP BY course, college, title HAVING SUM(cnt_employed) > 0",
            $b
        );
    }

    public function sectorStats(): array
    {
        return array_map(
            static fn ($r) => ['employment_sector' => $r['employment_sector'], 'count' => (int) $r['cnt']],
            $this->sorted($this->metric('any_sector', 'employment_sector'), 'employment_sector')
        );
    }

    public function locationStats(): array
    {
        return array_map(
            static fn ($r) => ['location_of_work' => $r['location_of_work'], 'count' => (int) $r['cnt']],
            $this->sorted($this->metric('any_location', 'location_of_work'), 'location_of_work')
        );
    }

    /** count DESC, then name (the live queries left ties in arbitrary order). */
    private function sorted(array $rows, string $key): array
    {
        usort($rows, static fn ($a, $b) => [(int) $b['cnt'], $a[$key]] <=> [(int) $a['cnt'], $b[$key]]);

        return $rows;
    }

    public function employedCount(): int
    {
        [$w, $b] = $this->scope();

        return (int) (DB::selectOne("SELECT COALESCE(SUM(employed_any), 0) AS c FROM rpt_program WHERE 1=1{$w}", $b)->c ?? 0);
    }

    public function unemployedCount(): int
    {
        [$w, $b] = $this->scope();

        return (int) (DB::selectOne("SELECT COALESCE(SUM(alumni_count - employed_any), 0) AS c FROM rpt_program WHERE 1=1{$w}", $b)->c ?? 0);
    }

    /** Distinct colleges for the campus scope (ignores college/year filters, like the live query). */
    public function distinctColleges(): array
    {
        [$w, $b] = $this->scope(false, false);

        return array_map(
            static fn ($r) => $r['college'],
            $this->all("SELECT DISTINCT college FROM rpt_program WHERE college <> ''{$w} ORDER BY college", $b)
        );
    }

    public function distinctYears(): array
    {
        [$w, $b] = $this->scope(false, false);

        return array_map(
            static fn ($r) => (int) $r['year_graduated'],
            $this->all('SELECT DISTINCT year_graduated FROM rpt_program WHERE year_graduated NOT IN (0, '.ReportingSummary::NO_YEAR.")".$w.' ORDER BY year_graduated DESC', $b)
        );
    }

    /* ---------------------------- Dashboard ---------------------------- */

    /** [college, graduates, employed] */
    public function graduatesPerCollege(): array
    {
        [$w, $b] = $this->scope(true, false);

        return $this->all(
            "SELECT college, SUM(alumni_count) AS graduates, SUM(active_alumni) AS employed FROM rpt_program WHERE 1=1{$w} GROUP BY college",
            $b
        );
    }

    /** [course, college] of every program that has alumni. */
    public function programKeys(): array
    {
        [$w, $b] = $this->scope(true, false);

        return $this->all("SELECT course, college FROM rpt_program WHERE 1=1{$w} GROUP BY course, college", $b);
    }

    /** [course, college, employment_status, cnt] - distinct alumni with an active job, per raw status. */
    public function activeStatusRows(): array
    {
        [$w, $b] = $this->scope(true, false);

        return $this->all(
            "SELECT course, college, dim_value AS employment_status, SUM(alumni_count) AS cnt FROM rpt_metric
             WHERE metric = 'active_status'{$w} GROUP BY course, college, dim_value",
            $b
        );
    }

    /** [course, college, cnt] - alumni with no active job. */
    public function unemployedRows(): array
    {
        [$w, $b] = $this->scope(true, false);

        return $this->all(
            "SELECT course, college, SUM(alumni_count - active_alumni) AS cnt FROM rpt_program WHERE 1=1{$w}
             GROUP BY course, college HAVING SUM(alumni_count - active_alumni) > 0",
            $b
        );
    }

    /** The three campus-grouped variants for many campuses (ids are already validated ints). */
    public function programKeysByCampus(array $ids): array
    {
        $in = implode(',', array_map('intval', $ids));

        return $this->all("SELECT campus_id, course, college FROM rpt_program WHERE campus_id IN ({$in}) GROUP BY campus_id, course, college");
    }

    public function activeStatusRowsByCampus(array $ids): array
    {
        $in = implode(',', array_map('intval', $ids));

        return $this->all(
            "SELECT campus_id, course, college, dim_value AS employment_status, SUM(alumni_count) AS cnt FROM rpt_metric
             WHERE metric = 'active_status' AND campus_id IN ({$in}) GROUP BY campus_id, course, college, dim_value"
        );
    }

    public function unemployedRowsByCampus(array $ids): array
    {
        $in = implode(',', array_map('intval', $ids));

        return $this->all(
            "SELECT campus_id, course, college, SUM(alumni_count - active_alumni) AS cnt FROM rpt_program
             WHERE campus_id IN ({$in}) GROUP BY campus_id, course, college HAVING SUM(alumni_count - active_alumni) > 0"
        );
    }

    public function collegesByCampus(array $ids): array
    {
        $in = implode(',', array_map('intval', $ids));

        return $this->all("SELECT DISTINCT campus_id, college FROM rpt_program WHERE campus_id IN ({$in}) AND college <> '' ORDER BY college");
    }

    /** [location_of_work, cnt] - distinct alumni with an active job at that location. */
    public function activeLocation(): array
    {
        return $this->metric('active_location', 'location_of_work');
    }

    public function activeSector(): array
    {
        return $this->metric('active_sector', 'employment_sector');
    }

    /** [location_of_work, course, n] - distinct alumni with a current job. */
    public function currentLocationByCourse(): array
    {
        return $this->metric('cur_location', 'location_of_work', ', course', ', course') ?: [];
    }

    public function currentSectorByCourse(): array
    {
        return $this->metric('cur_sector', 'employment_sector', ', course', ', course') ?: [];
    }

    /** [course, title, cnt] for currently-employed alumni. */
    public function currentCourseJobTitleCounts(): array
    {
        [$w, $b] = $this->scope(true, false);

        return $this->all("SELECT course, title, SUM(cnt_current) AS cnt FROM rpt_title_all WHERE 1=1{$w} GROUP BY course, title", $b);
    }

    /** ['total' => alumni, 'yesterday' => alumni created yesterday (as of the last build day)] */
    public function alumniTotals(): array
    {
        [$w, $b] = $this->scope(true, false);
        $r = DB::selectOne("SELECT COALESCE(SUM(alumni_count), 0) AS total, COALESCE(SUM(created_yesterday), 0) AS yesterday FROM rpt_program WHERE 1=1{$w}", $b);

        return ['total' => (int) $r->total, 'yesterday' => (int) $r->yesterday];
    }

    /**
     * ['total' => applications, 'yesterday' => ...] for the campus scope, or null when not computed yet (or under a college filter,
     * which the counts do not carry) - the caller then runs the live join.
     */
    public function applicationTotals(): ?array
    {
        if ($this->college !== null) {
            return null;
        }
        $q = DB::table('rpt_state');
        if ($this->campusId !== null) {
            $q->where('campus_id', $this->campusId);
        }
        $r = $q->selectRaw('COALESCE(SUM(applications_total), 0) AS t, COALESCE(SUM(applications_yesterday), 0) AS y, COUNT(*) AS n, COUNT(applications_at) AS ready')->first();
        if ((int) $r->n === 0 || (int) $r->ready < (int) $r->n) {
            return null;
        }

        return ['total' => (int) $r->t, 'yesterday' => (int) $r->y];
    }

    /** [city, province, course, n, employed]; null under a college filter (rpt_location has no college: caller uses the live query). */
    public function locationClusters(): ?array
    {
        if ($this->college !== null) {
            return null;
        }
        $w = $this->campusId !== null ? ' WHERE campus_id = ?' : '';

        return $this->all(
            "SELECT city, province, course, SUM(alumni_count) AS n, SUM(employed_count) AS employed FROM rpt_location{$w} GROUP BY city, province, course",
            $this->campusId !== null ? [$this->campusId] : []
        );
    }
}
