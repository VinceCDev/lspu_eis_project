<?php

namespace App\Console\Commands;

use App\Models\DashboardStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DEV tool. Proves the C1–C4 dashboard optimizations return the SAME
 * numbers as the pre-optimization code, and benchmarks query count / DB
 * time / memory for each critical path.
 *
 *   php artisan dashboard:perf
 *
 * It re-runs the ORIGINAL (slow) SQL inline and diffs it against the new
 * implementation, so a mismatch is caught before anything ships.
 */
class DashboardPerfCheckCommand extends Command
{
    protected $signature = 'dashboard:perf {--campus= : scope to one campus id}';

    protected $description = 'Regression-check + benchmark the C1–C4 dashboard query optimizations.';

    private ?int $campusId = null;

    public function handle(): int
    {
        $this->campusId = $this->option('campus') !== null ? (int) $this->option('campus') : null;
        $scope = $this->campusId === null ? '' : ' AND a.campus_id = '.$this->campusId;
        $scopeNoAlias = $this->campusId === null ? '' : ' AND campus_id = '.$this->campusId;

        $this->line('Row counts: alumni='.DB::table('alumni')->count()
            .'  alumni_experience='.DB::table('alumni_experience')->count());
        $this->newLine();

        $stats = new DashboardStats($this->campusId);

        /* ---------------- C2: graduatesPerCollege ---------------- */
        $oldGrad = $this->oldGraduatesPerCollege($scope, $scopeNoAlias);
        [$newGrad, $m2] = $this->measure(fn () => $this->invoke($stats, 'graduatesPerCollege'));
        $this->compare('C2 graduatesPerCollege', $this->normalizeGrad($oldGrad), $this->normalizeGrad($newGrad), $m2);

        /* ---------------- C3: employmentStatusPerProgram -------- */
        $oldEsp = $this->oldEmploymentStatusPerProgram($scope);
        [$newEsp, $m3] = $this->measure(fn () => $stats->employmentStatusPerProgram());
        $this->compare('C3 employmentStatusPerProgram', $oldEsp, $newEsp, $m3);

        /* ---------------- C1: alumniMap totals ----------------- */
        $oldMap = $this->oldAlumniMapTotals($scope);
        [$newMapRaw, $m1] = $this->measure(fn () => $stats->alumniMap());
        $newMap = ['alumni' => 0, 'employed' => 0];
        foreach ($newMapRaw as $c) {
            $newMap['alumni'] += $c['count'];
            $newMap['employed'] += $c['employed'];
        }
        // Compare the numbers that must not change (plotted alumni + employed).
        // Marker count can shift slightly because SQL GROUP BY merges
        // location strings differing only by case / trailing space.
        $this->compare('C1 alumniMap (plotted alumni + employed)',
            ['alumni' => $oldMap['alumni'], 'employed' => $oldMap['employed']], $newMap, $m1);
        $this->line("    markers: old(PHP string keys)={$oldMap['locations']}  new(SQL GROUP BY)=".count($newMapRaw)
            .'   payload rows: old='.$oldMap['alumni'].'  new='.count($newMapRaw).' cluster objects');

        /* ---------------- C4: employmentStatusByCampus --------- */
        $campusIds = DB::table('campus')->pluck('campus_id')->map(fn ($x) => (int) $x)->all();
        $oldC4 = [];
        $oldC4Queries = $this->countQueries(function () use ($campusIds, &$oldC4) {
            foreach ($campusIds as $cid) {
                $oldC4[$cid] = (new DashboardStats($cid))->employmentStatusPerProgram();
            }
        });
        [$newC4, $m4] = $this->measure(fn () => (new DashboardStats())->employmentStatusPerProgramByCampus($campusIds));
        $c4Match = true;
        foreach ($campusIds as $cid) {
            if (($oldC4[$cid] ?? []) != ($newC4[$cid] ?? [])) {
                $c4Match = false;
            }
        }
        $this->line(($c4Match ? '<info>[MATCH]</info>' : '<error>[MISMATCH]</error>')
            .' C4 employmentStatusByCampus  '
            ."old_queries={$oldC4Queries}  new_queries={$m4['queries']}  new_db_ms={$m4['ms']}  new_mem_mb={$m4['mem']}");
        if (!$c4Match) {
            foreach ($campusIds as $cid) {
                if (($oldC4[$cid] ?? []) != ($newC4[$cid] ?? [])) {
                    $this->error("   campus {$cid} differs");
                }
            }
        }

        /* ---------------- full `stats` payload: OLD vs NEW ----- */
        $this->newLine();

        [$oldPayload, $mOld] = $this->measure(fn () => $this->oldFullStatsPayload($scope));
        $oldJson = json_encode($oldPayload);
        $this->line('<comment>OLD</comment> stats payload: queries='.$mOld['queries'].'  db_ms='.$mOld['ms']
            .'  build_mem_mb='.$mOld['mem'].'  json_kb='.round(strlen($oldJson) / 1024, 1)
            .'  peak_mem_mb='.round(memory_get_peak_usage(true) / 1048576, 1));

        gc_collect_cycles();
        [$payload, $mAll] = $this->measure(function () use ($stats) {
            return array_merge($stats->chartBreakdowns(), $stats->totalsForCards(), ['alumni_map' => $stats->alumniMap()]);
        });
        $json = json_encode($payload);
        $this->line('<info>NEW</info> stats payload: queries='.$mAll['queries'].'  db_ms='.$mAll['ms']
            .'  build_mem_mb='.$mAll['mem'].'  json_kb='.round(strlen($json) / 1024, 1)
            .'  peak_mem_mb='.round(memory_get_peak_usage(true) / 1048576, 1));

        return self::SUCCESS;
    }

    /* ============ helpers ============ */

    private function invoke(object $obj, string $method): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);

        return $ref->invoke($obj);
    }

    private function measure(callable $fn): array
    {
        $queries = 0;
        $ms = 0.0;
        DB::listen(function ($q) use (&$queries, &$ms) {
            $queries++;
            $ms += $q->time;
        });
        $m0 = memory_get_usage();
        $result = $fn();
        DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

        return [$result, [
            'queries' => $queries,
            'ms' => round($ms, 1),
            'mem' => round((memory_get_usage() - $m0) / 1048576, 2),
        ]];
    }

    private function countQueries(callable $fn): int
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        $fn();
        DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

        return $n;
    }

    private function compare(string $label, array $old, array $new, array $m): void
    {
        $match = $old == $new;
        $this->line(($match ? '<info>[MATCH]</info>' : '<error>[MISMATCH]</error>')." {$label}  "
            ."queries={$m['queries']}  db_ms={$m['ms']}  mem_mb={$m['mem']}");
        if (!$match) {
            $this->line('   OLD: '.json_encode($old));
            $this->line('   NEW: '.json_encode($new));
        }
    }

    private function normalizeGrad(array $g): array
    {
        ksort($g);

        return $g;
    }

    /* ---- original (pre-C2) graduatesPerCollege ---- */
    private function oldGraduatesPerCollege(string $scope, string $scopeNoAlias): array
    {
        $abbr = new \ReflectionMethod(DashboardStats::class, 'abbreviateCollege');
        $abbr->setAccessible(true);
        $ds = new DashboardStats($this->campusId);

        $colleges = [];
        foreach (DB::select("SELECT college, COUNT(*) as graduates FROM alumni a WHERE 1=1{$scope} GROUP BY college") as $row) {
            $colleges[$abbr->invoke($ds, $row->college)] = ['graduates' => (int) $row->graduates, 'employed' => 0];
        }
        $employed = [];
        foreach (DB::select('SELECT DISTINCT alumni_id FROM alumni_experience WHERE current = 1 OR (end_date IS NULL OR end_date >= CURDATE())') as $row) {
            $employed[(int) $row->alumni_id] = true;
        }
        foreach (DB::select("SELECT alumni_id, college FROM alumni a WHERE 1=1{$scope}") as $row) {
            $c = $abbr->invoke($ds, $row->college);
            if (isset($employed[(int) $row->alumni_id]) && isset($colleges[$c])) {
                $colleges[$c]['employed']++;
            }
        }

        return $colleges;
    }

    /* ---- original (pre-C3) employmentStatusPerProgram, Unemployed via NOT IN ---- */
    private function oldEmploymentStatusPerProgram(string $scope): array
    {
        $labels = ['Probational', 'Contractual', 'Regular', 'Self-employed', 'Unemployed'];
        $programs = [];
        $norm = fn ($college, $course) => \App\Models\Report::normalizeProgram($college, $course);
        // reuse the model's private abbreviateCourse via reflection
        $abbr = new \ReflectionMethod(DashboardStats::class, 'abbreviateCourse');
        $abbr->setAccessible(true);
        $ds = new DashboardStats($this->campusId);

        foreach (DB::select("SELECT course, college FROM alumni a WHERE 1=1{$scope} GROUP BY course, college") as $r) {
            $p = $abbr->invoke($ds, $norm($r->college, $r->course));
            $programs[$p] ??= array_fill_keys($labels, 0);
        }
        $today = date('Y-m-d');
        foreach (DB::select("SELECT a.course, a.college, e.employment_status, COUNT(DISTINCT a.alumni_id) as cnt
            FROM alumni a JOIN alumni_experience e ON a.alumni_id = e.alumni_id
            WHERE (e.current = 1 OR (e.end_date IS NULL OR e.end_date >= ?)){$scope}
            GROUP BY a.course, a.college, e.employment_status", [$today]) as $r) {
            $p = $abbr->invoke($ds, $norm($r->college, $r->course));
            if (isset($programs[$p][$r->employment_status])) {
                $programs[$p][$r->employment_status] += (int) $r->cnt;
            }
        }
        foreach (DB::select("SELECT a.course, a.college, COUNT(*) as cnt FROM alumni a
            WHERE a.alumni_id NOT IN (SELECT DISTINCT alumni_id FROM alumni_experience
                WHERE current = 1 OR (end_date IS NULL OR end_date >= CURDATE())){$scope}
            GROUP BY a.course, a.college") as $r) {
            $p = $abbr->invoke($ds, $norm($r->college, $r->course));
            if (isset($programs[$p])) {
                $programs[$p]['Unemployed'] += (int) $r->cnt;
            }
        }

        return $programs;
    }

    /**
     * The whole pre-C1..C4 `stats` payload, rebuilt inline: OLD alumniMap
     * (row-per-alumnus + description) + OLD graduatesPerCollege + OLD
     * employmentStatusPerProgram, plus the parts that C1–C4 did NOT touch
     * (work/sector distributions, courses_per_*, totalsForCards) taken from
     * the live model so both payloads stay comparable.
     */
    private function oldFullStatsPayload(string $scope): array
    {
        $ds = new DashboardStats($this->campusId);

        // OLD alumniMap — every located alumnus + latest experience incl. description
        $rows = DB::select("SELECT a.alumni_id, a.first_name, a.middle_name, a.last_name, a.profile_pic, a.course,
                a.year_graduated, a.city, a.province, a.college FROM alumni a
            WHERE a.city IS NOT NULL AND a.city != '' AND a.province IS NOT NULL AND a.province != ''{$scope}");
        $latest = [];
        foreach (DB::select("SELECT alumni_id, title, company, start_date, end_date, description, employment_status, location_of_work
            FROM alumni_experience WHERE current = 1 OR (end_date IS NULL OR end_date >= CURDATE())
            ORDER BY alumni_id, start_date DESC") as $r) {
            $aid = (int) $r->alumni_id;
            $latest[$aid] ??= $r;
        }
        $map = [];
        foreach ($rows as $r) {
            $exp = $latest[(int) $r->alumni_id] ?? null;
            $map[$r->city.', '.$r->province][] = [
                'name' => trim($r->first_name.' '.$r->middle_name.' '.$r->last_name),
                'profile_pic' => $r->profile_pic ? 'uploads/profile_picture/'.$r->profile_pic : null,
                'course' => $r->course, 'college' => $r->college, 'year_graduated' => $r->year_graduated,
                'work' => $exp ? $exp->title.' at '.$exp->company : '',
                'work_details' => $exp ? (array) $exp : null,
                'status' => $exp ? 'Employed' : 'Unemployed',
            ];
        }

        $breakdowns = $ds->chartBreakdowns();               // uses NEW C2/C3 internally; swap the two we changed:
        $breakdowns['graduates_per_college'] = $this->oldGraduatesPerCollege($scope, '');
        $breakdowns['employment_status_per_program'] = $this->oldEmploymentStatusPerProgram($scope);

        return array_merge($breakdowns, $ds->totalsForCards(), ['alumni_map' => $map]);
    }

    /* ---- original (pre-C1) alumniMap, reduced to comparable totals ---- */
    private function oldAlumniMapTotals(string $scope): array
    {
        $rows = DB::select("SELECT a.alumni_id, a.city, a.province FROM alumni a
            WHERE a.city IS NOT NULL AND a.city != '' AND a.province IS NOT NULL AND a.province != ''{$scope}");
        $employed = [];
        foreach (DB::select("SELECT alumni_id FROM alumni_experience
            WHERE current = 1 OR (end_date IS NULL OR end_date >= CURDATE())") as $r) {
            $employed[(int) $r->alumni_id] = true;
        }
        $locations = [];
        $employedCount = 0;
        foreach ($rows as $r) {
            $locations[$r->city.', '.$r->province] = true;
            if (isset($employed[(int) $r->alumni_id])) {
                $employedCount++;
            }
        }

        return ['locations' => count($locations), 'alumni' => count($rows), 'employed' => $employedCount];
    }
}
