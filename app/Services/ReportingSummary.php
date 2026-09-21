<?php

namespace App\Services;

use App\Jobs\RebuildReportingSummary;
use App\Support\HeavyCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pre-aggregated counts behind the Dashboard and Reports pages (tables rpt_program / rpt_metric / rpt_title / rpt_location,
 * freshness in rpt_state).
 *
 * WHY: every page figure used to be a GROUP BY / COUNT(DISTINCT) over alumni JOIN alumni_experience. That is O(alumni) per
 * request - measured 98 s (dashboard, cold) and 40 s (report summary) at 1M alumni, > 180 s at 2M - and no index fixes it
 * (the join + DISTINCT + sort has to visit ~600k rows). The pages now read the aggregates; ~2-40 thousand summary rows
 * regardless of how many alumni exist.
 *
 * GRAIN: one partition per campus (campus_id 0 = alumni with no campus). A partition is rebuilt as a unit, in the
 * background, by RebuildReportingSummary: the aggregates are computed with plain (non-locking, READ COMMITTED) SELECTs into
 * session temp tables, then swapped in with one short DELETE+INSERT transaction, so readers see the old or the new numbers
 * and never a half-built mixture, and concurrent imports are never blocked.
 *
 * FRESHNESS: anything that changes alumni data calls markDirty(campus). A dirty partition is rebuilt within
 * config('reporting.min_rebuild_interval') seconds (imports: immediately) and once a day (the "employed" definition depends
 * on CURDATE()). Until a partition has been built, and whenever REPORTING_SUMMARY=false, the pages fall back to the original
 * live queries - so this layer can be switched off without redeploying.
 */
final class ReportingSummary
{
    public const NO_CAMPUS = 0;
    public const NO_YEAR = -1;

    /** @var array<int, object>|null per-request memo of rpt_state rows keyed by campus_id */
    private static ?array $states = null;

    public static function enabled(): bool
    {
        return (bool) config('reporting.use_summary', true);
    }

    /** Forget the per-request memo (tests, long-running workers). */
    public static function flushMemo(): void
    {
        self::$states = null;
        self::$refreshChecked = false;
    }

    /** @return array<int, object> */
    private static function states(): array
    {
        if (self::$states === null) {
            self::$states = [];
            foreach (DB::table('rpt_state')->get() as $row) {
                self::$states[(int) $row->campus_id] = $row;
            }
        }

        return self::$states;
    }

    /**
     * True when the summary can answer for this scope: every partition in scope has been built (campus null = ALL campuses,
     * including alumni without a campus). A missing table (migration not run yet) simply means "not ready".
     */
    public static function ready(?int $campusId): bool
    {
        if (!self::enabled()) {
            return false;
        }
        try {
            $states = self::states();
        } catch (\Throwable) {
            return false;
        }
        $need = $campusId !== null ? [$campusId] : self::allPartitionIds();
        foreach ($need as $id) {
            if (!isset($states[$id]) || $states[$id]->built_at === null) {
                return false;
            }
        }
        self::maybeScheduleRefresh();

        return true;
    }

    /** @return int[] every campus id + the "no campus" partition */
    public static function allPartitionIds(): array
    {
        return array_merge(
            [self::NO_CAMPUS],
            array_map('intval', DB::table('campus')->orderBy('campus_id')->pluck('campus_id')->all())
        );
    }

    /* ------------------------------------------------------------------ *
     *  Invalidation
     * ------------------------------------------------------------------ */

    /**
     * Something in this campus's alumni data changed. $rebuildNow (bulk imports) queues the rebuild immediately; otherwise it
     * happens on the next refresh check once `min_rebuild_interval` has passed. Cheap: one upsert.
     */
    public static function markDirty(?int $campusId, bool $rebuildNow = false): void
    {
        $id = $campusId ?? self::NO_CAMPUS;
        try {
            DB::statement(
                'INSERT INTO rpt_state (campus_id, dirty_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE dirty_at = ?',
                [$id, $stamp = now()->format('Y-m-d H:i:s.v'), $stamp]
            );
            self::$states = null;
        } catch (\Throwable $e) {
            report($e);   // never fail an alumni edit because the summary bookkeeping failed

            return;
        }
        if ($rebuildNow) {
            self::dispatchRebuild($id);
        }
    }

    public static function markDirtyForAlumni(int $alumniId): void
    {
        try {
            $campus = DB::table('alumni')->where('alumni_id', $alumniId)->value('campus_id');
            self::markDirty($campus !== null ? (int) $campus : null);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function markDirtyForUser(int $userId): void
    {
        try {
            $campus = DB::table('alumni')->where('user_id', $userId)->value('campus_id');
            self::markDirty($campus !== null ? (int) $campus : null);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function dispatchRebuild(int $partition): void
    {
        RebuildReportingSummary::dispatch($partition);
    }

    /**
     * Partitions that need a rebuild: never built, changed since the last build started (and not rebuilt in the last
     * `min_rebuild_interval` seconds), or built on an earlier day.
     *
     * @return int[]
     */
    public static function duePartitions(): array
    {
        $today = date('Y-m-d');
        $minAge = now()->subSeconds((int) config('reporting.min_rebuild_interval', 60));
        $due = [];
        foreach (DB::table('rpt_state')->get() as $s) {
            $id = (int) $s->campus_id;
            $neverBuilt = $s->built_at === null;
            $stale = $s->dirty_at !== null && ($s->build_started_at === null || $s->dirty_at > $s->build_started_at);
            $oldDay = $s->built_on !== null && $s->built_on < $today;
            if ($neverBuilt || $oldDay || ($stale && ($s->built_at === null || $s->built_at <= $minAge->format('Y-m-d H:i:s.v')))) {
                $due[] = $id;
            }
        }

        return $due;
    }

    /** Called from the read path (at most every 30 s per server): queue whatever is due. Never throws, never blocks. */
    private static bool $refreshChecked = false;

    private static function maybeScheduleRefresh(): void
    {
        if (self::$refreshChecked) {
            return;   // once per request/process is plenty (ready() is called by every model instance)
        }
        self::$refreshChecked = true;
        try {
            if (!Cache::add('rpt:refresh_check', 1, 30)) {
                return;
            }
            foreach (self::duePartitions() as $id) {
                self::dispatchRebuild($id);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /* ------------------------------------------------------------------ *
     *  Rebuild
     * ------------------------------------------------------------------ */

    /**
     * Recomputes one partition from the raw tables. Returns ['ms' => int, 'alumni' => int, 'rows' => [table => n]].
     */
    public function rebuild(int $partition): array
    {
        $t0 = microtime(true);
        $startedAt = now()->format('Y-m-d H:i:s.v');
        $today = date('Y-m-d');
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $todayStart = date('Y-m-d 00:00:00');

        DB::table('rpt_state')->updateOrInsert(['campus_id' => $partition], ['build_started_at' => $startedAt]);

        $conn = DB::connection();
        $noYear = (int) self::NO_YEAR;   // inlined: a placeholder in SELECT and GROUP BY are different expressions to ONLY_FULL_GROUP_BY
        // READ COMMITTED: INSERT..SELECT / CREATE..SELECT take no shared locks on the source rows, so a concurrent import's
        // INSERTs into alumni are never blocked by (or deadlocked with) the rebuild.
        $conn->statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        try {
            [$cond, $bind] = $partition === self::NO_CAMPUS ? ['a.campus_id IS NULL', []] : ['a.campus_id = ?', [$partition]];
            $conn->statement('DROP TEMPORARY TABLE IF EXISTS tmp_rpt_flags');
            $conn->statement('DROP TEMPORARY TABLE IF EXISTS tmp_rpt_exp');

            // Per-alumnus facts about their jobs (computed once, used by rpt_program and rpt_location).
            $conn->statement('CREATE TEMPORARY TABLE tmp_rpt_flags (
                alumni_id INT NOT NULL PRIMARY KEY, cur_rows INT NOT NULL, cur_emp_rows INT NOT NULL, any_emp TINYINT NOT NULL, active TINYINT NOT NULL
            ) ENGINE=InnoDB');
            $conn->insert(
                "INSERT INTO tmp_rpt_flags
                 SELECT e.alumni_id,
                        COALESCE(SUM(e.current = 1), 0),
                        COALESCE(SUM(e.current = 1 AND e.employment_status IS NOT NULL AND e.employment_status <> ''), 0),
                        COALESCE(MAX(e.employment_status IS NOT NULL AND e.employment_status <> ''), 0),
                        COALESCE(MAX(e.current = 1 OR e.end_date IS NULL OR e.end_date >= CURDATE()), 0)
                 FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id
                 WHERE {$cond}
                 GROUP BY e.alumni_id",
                $bind
            );

            // One row per job with the alumnus's grouping columns, so each metric below is a plain GROUP BY on a small table.
            $conn->statement('CREATE TEMPORARY TABLE tmp_rpt_exp (
                alumni_id INT NOT NULL, y INT NOT NULL, college VARCHAR(100) NOT NULL, course VARCHAR(100) NOT NULL,
                cur TINYINT NOT NULL, title VARCHAR(255) NOT NULL, st VARCHAR(50) NULL, loc VARCHAR(50) NULL, sec VARCHAR(50) NULL, active TINYINT NOT NULL,
                KEY (alumni_id)
            ) ENGINE=InnoDB');
            $conn->insert(
                "INSERT INTO tmp_rpt_exp
                 SELECT e.alumni_id, COALESCE(a.year_graduated, {$noYear}), a.college, a.course, COALESCE(e.current = 1, 0), e.title,
                        e.employment_status, e.location_of_work, e.employment_sector,
                        COALESCE(e.current = 1 OR e.end_date IS NULL OR e.end_date >= CURDATE(), 0)
                 FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id
                 WHERE {$cond}",
                $bind
            );

            $counts = [];
            $conn->transaction(function () use ($conn, $partition, $cond, $bind, $noYear, $yesterdayStart, $todayStart, &$counts) {
                foreach (['rpt_program', 'rpt_metric', 'rpt_title', 'rpt_title_all', 'rpt_location'] as $table) {
                    $conn->delete("DELETE FROM {$table} WHERE campus_id = ?", [$partition]);
                }

                $counts['rpt_program'] = $conn->affectingStatement(
                    "INSERT INTO rpt_program (campus_id, year_graduated, college, course, alumni_count, created_yesterday, current_rows, employed_current_rows, employed_any, active_alumni)
                     SELECT ?, COALESCE(a.year_graduated, {$noYear}), a.college, a.course, COUNT(*),
                            SUM(a.created_at >= ? AND a.created_at < ?),
                            SUM(GREATEST(COALESCE(f.cur_rows, 0), 1)), SUM(COALESCE(f.cur_emp_rows, 0)),
                            SUM(COALESCE(f.any_emp, 0)), SUM(COALESCE(f.active, 0))
                     FROM alumni a LEFT JOIN tmp_rpt_flags f ON f.alumni_id = a.alumni_id
                     WHERE {$cond}
                     GROUP BY COALESCE(a.year_graduated, {$noYear}), a.college, a.course",
                    [$partition, $yesterdayStart, $todayStart, ...$bind]
                );

                // metric => [dimension column, row filter, dim expression]. Every count is DISTINCT alumni.
                $metrics = [
                    'active_status' => ['st', 'active = 1', "COALESCE(st, '')"],
                    'active_location' => ['loc', 'active = 1', "COALESCE(loc, '')"],
                    'active_sector' => ['sec', 'active = 1', "COALESCE(sec, '')"],
                    'cur_location' => ['loc', 'cur = 1', "COALESCE(loc, '')"],
                    'cur_sector' => ['sec', 'cur = 1', "COALESCE(sec, '')"],
                    'any_location' => ['loc', "st IS NOT NULL AND st <> ''", "COALESCE(loc, 'Not Specified')"],
                    'any_sector' => ['sec', "st IS NOT NULL AND st <> ''", "COALESCE(sec, 'Not Specified')"],
                ];
                $counts['rpt_metric'] = 0;
                foreach ($metrics as $name => [, $filter, $dim]) {
                    $counts['rpt_metric'] += $conn->affectingStatement(
                        "INSERT INTO rpt_metric (metric, campus_id, year_graduated, college, course, dim_value, alumni_count)
                         SELECT ?, ?, y, college, course, {$dim}, COUNT(DISTINCT alumni_id)
                         FROM tmp_rpt_exp WHERE {$filter}
                         GROUP BY y, college, course, {$dim}",
                        [$name, $partition]
                    );
                }

                $counts['rpt_title'] = $conn->affectingStatement(
                    "INSERT INTO rpt_title (campus_id, year_graduated, college, course, title, cnt_current, cnt_employed)
                     SELECT ?, y, college, course, title, COUNT(*),
                            SUM(st IS NOT NULL AND st <> '' AND title <> '' AND course <> '')
                     FROM tmp_rpt_exp WHERE cur = 1
                     GROUP BY y, college, course, title",
                    [$partition]
                );

                $counts['rpt_title_all'] = $conn->affectingStatement(
                    "INSERT INTO rpt_title_all (campus_id, college, course, title, cnt_current, cnt_employed)
                     SELECT ?, college, course, title, COUNT(*),
                            SUM(st IS NOT NULL AND st <> '' AND title <> '' AND course <> '')
                     FROM tmp_rpt_exp WHERE cur = 1
                     GROUP BY college, course, title",
                    [$partition]
                );

                $counts['rpt_location'] = $conn->affectingStatement(
                    "INSERT INTO rpt_location (campus_id, city, province, course, alumni_count, employed_count)
                     SELECT ?, a.city, a.province, a.course, COUNT(*), SUM(COALESCE(f.active, 0))
                     FROM alumni a LEFT JOIN tmp_rpt_flags f ON f.alumni_id = a.alumni_id
                     WHERE a.city <> '' AND a.province <> '' AND {$cond}
                     GROUP BY a.city, a.province, a.course",
                    [$partition, ...$bind]
                );
            });

            $alumni = (int) DB::table('rpt_program')->where('campus_id', $partition)->sum('alumni_count');
            // Active = all - (Inactive/Pending). The complement is driven from the small (user_role, status) index range instead of
            // joining every alumnus to its user row (measured at 300k alumni: 2.6-3.4 s -> 0.19 s).
            $inactive = (int) $conn->selectOne(
                "SELECT COUNT(*) AS c FROM user u JOIN alumni a ON a.user_id = u.user_id WHERE u.user_role = 'alumni' AND u.status <> 'Active' AND {$cond}",
                $bind
            )->c;
            $activeUsers = max(0, $alumni - $inactive);
        } finally {
            $conn->statement('DROP TEMPORARY TABLE IF EXISTS tmp_rpt_flags');
            $conn->statement('DROP TEMPORARY TABLE IF EXISTS tmp_rpt_exp');
            $conn->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        $ms = (int) round((microtime(true) - $t0) * 1000);
        DB::table('rpt_state')->where('campus_id', $partition)->update([
            'built_at' => now()->format('Y-m-d H:i:s.v'),
            'built_on' => $today,
            'build_ms' => $ms,
            'alumni_rows' => $alumni,
            'active_users' => $activeUsers,
        ]);
        self::$states = null;

        // Cached dashboard/report payloads of this campus (and the "all campuses" ones) are now out of date: flag exactly
        // those stale. They keep being served instantly while ONE refresh per payload runs - and that refresh reads the
        // summary, so it takes milliseconds.
        HeavyCache::markStale(self::cacheScopes($partition));

        return ['ms' => $ms, 'alumni' => $alumni, 'rows' => $counts];
    }

    /**
     * Alumni with an 'Active' account in this campus scope (null = all), as of the last build - the Alumni page's unfiltered
     * total. null when the summary can't answer (not built / switched off): the caller counts live.
     */
    public static function activeUsers(?int $campusId): ?int
    {
        if (!self::ready($campusId)) {
            return null;
        }
        $q = DB::table('rpt_state');
        if ($campusId !== null) {
            $q->where('campus_id', $campusId);
        }

        return (int) $q->sum('active_users');
    }

    /**
     * Dashboard card "applications" (total / yesterday) per campus. They join `applications` to `alumni` (one random alumni lookup per
     * application: measured 5-6 s per call at 5M alumni with a small buffer pool, and it was the reason the cold dashboard took
     * 12-18 s there), so they are computed here, in the background, and read from rpt_state.
     */
    public static function refreshApplications(): void
    {
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $todayStart = date('Y-m-d 00:00:00');
        $rows = DB::select(
            'SELECT COALESCE(a.campus_id, 0) AS c, COUNT(*) AS total, COALESCE(SUM(app.applied_at >= ? AND app.applied_at < ?), 0) AS y
             FROM applications app JOIN alumni a ON a.alumni_id = app.alumni_id GROUP BY COALESCE(a.campus_id, 0)',
            [$yesterdayStart, $todayStart]
        );
        $now = now()->format('Y-m-d H:i:s.v');
        DB::table('rpt_state')->update(['applications_total' => 0, 'applications_yesterday' => 0, 'applications_at' => $now]);
        foreach ($rows as $r) {
            DB::table('rpt_state')->updateOrInsert(['campus_id' => (int) $r->c], [
                'applications_total' => (int) $r->total, 'applications_yesterday' => (int) $r->y, 'applications_at' => $now,
            ]);
        }
        self::$states = null;
    }

    /** Refresh the application counts when they are older than 5 minutes or from before today ("yesterday" moves at midnight). */
    public static function refreshApplicationsIfDue(): bool
    {
        $oldest = DB::table('rpt_state')->min('applications_at');
        if ($oldest !== null && $oldest > now()->subMinutes(5)->format('Y-m-d H:i:s.v') && substr($oldest, 0, 10) === date('Y-m-d')) {
            return false;
        }
        self::refreshApplications();

        return true;
    }

    /** HeavyCache scopes affected by a change in this partition. */
    public static function cacheScopes(?int $campusId): array
    {
        return $campusId === null || $campusId === self::NO_CAMPUS ? ['all'] : ['campus:'.$campusId, 'all'];
    }

    /** HeavyCache scope a payload for this campus filter depends on (null = all campuses). */
    public static function scopeFor(?int $campusId): string
    {
        return $campusId === null ? 'all' : 'campus:'.$campusId;
    }
}
