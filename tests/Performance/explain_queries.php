<?php
/**
 * Captures every SQL statement issued by the Dashboard / Reports / Alumni-list code paths (by calling the REAL model and
 * service methods under DB::listen — nothing is re-typed here), then EXPLAINs each distinct statement and, with --analyze,
 * runs EXPLAIN ANALYZE to get actual time and rows.
 *
 *   source tests/Performance/env.sh
 *   php tests/Performance/explain_queries.php --out=tests/Performance/results/explain_1m.md [--analyze] [--campus=8]
 *
 * Flags in the output: full table scan (type=ALL), full index scan (type=index), "Using temporary", "Using filesort".
 */
$o = getopt('', ['out:', 'analyze', 'campus::']);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!str_contains(config('database.connections.mysql.database'), 'perf')) {
    fwrite(STDERR, "not a perf DB\n");
    exit(1);
}
ini_set('memory_limit', '4G');
set_time_limit(0);

use App\Models\Alumni;
use App\Models\DashboardStats;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;

$campus = (int) ($o['campus'] ?? 8);
$captured = [];
$current = '';
DB::listen(function ($q) use (&$captured, &$current) {
    $key = md5($q->sql);
    $captured[$key] ??= ['where' => $current, 'sql' => $q->sql, 'bindings' => $q->bindings, 'ms' => 0.0, 'n' => 0];
    $captured[$key]['ms'] += $q->time;
    $captured[$key]['n']++;
});
$run = function (string $label, callable $fn) use (&$current) {
    $current = $label;
    $t = microtime(true);
    try {
        $fn();
    } catch (\Throwable $e) {
        fwrite(STDERR, "[$label] ".substr($e->getMessage(), 0, 160)."\n");
    }
    fwrite(STDERR, sprintf("%-46s %.1fs\n", $label, microtime(true) - $t));
};

// --- Dashboard (superadmin scope + campus scope) ---
foreach ([null, $campus] as $cid) {
    $s = $cid === null ? 'all' : "campus{$cid}";
    $ds = new DashboardStats($cid);
    $run("dashboard.chartBreakdowns[$s]", fn () => $ds->chartBreakdowns());
    $run("dashboard.totalsForCards[$s]", fn () => $ds->totalsForCards());
    $run("dashboard.alumniMap[$s]", fn () => $ds->alumniMap());
    $run("dashboard.currentCourseJobTitles[$s]", fn () => count(method_exists($ds, 'currentCourseJobTitleCounts') ? $ds->currentCourseJobTitleCounts() : $ds->currentCourseJobTitles()));
}
$run('dashboard.employmentStatusByCampus', function () {
    $ids = array_map(fn ($c) => (int) $c['campus_id'], (new App\Models\Campus())->all());
    $ds = new DashboardStats();
    $ds->employmentStatusPerProgramByCampus($ids);
    $ds->collegesByCampus($ids);
});
$run('dashboard.alumniAtLocation', fn () => (new DashboardStats(null))->alumniAtLocation('San Pablo City', 'Laguna', 20, 0));
$run('dashboard.collegeEmploymentStatus', fn () => (new DashboardStats($campus, 'College of Computer Studies'))->employmentStatusPerProgram());

// --- Reports ---
foreach ([[null, null, null], [$campus, null, null], [null, null, 2020]] as [$cid, $college, $yr]) {
    $s = 'campus='.($cid ?? 'all').' year='.($yr ?? 'all');
    $svc = fn () => new ReportService(null, null, $cid, $college, $yr);
    $run("report.summary[$s]", fn () => $svc()->summary());
    $run("report.colleges/years[$s]", function () use ($cid, $yr) {
        (new Report($cid))->distinctColleges();
        (new Report($cid))->distinctYears();
    });
}
$run("report.fullData[campus=$campus]", fn () => count((new ReportService(null, null, $campus, null, null))->fullReportData()));

// --- Alumni list / search / pagination ---
$am = new Alumni();
$run('alumni.paginatedList search=Santos', function () use ($am) {
    $am->allByStatusPaginated('Active', null, 25, 0, 'Santos');
    $am->countByStatus('Active', null, 'Santos');
});
$run('alumni.paginatedList search=Zq (rare prefix, capped count)', function () use ($am) {
    $am->allByStatusPaginated('Active', null, 25, 0, 'Zq');
    $am->countByStatus('Active', null, 'Zq', [], 10000);
});
$run('alumni.paginatedList search=Garcia (common prefix, capped count)', function () use ($am) {
    $am->allByStatusPaginated('Active', null, 25, 0, 'Garcia');
    $am->countByStatus('Active', null, 'Garcia', [], 10000);
});
$run("alumni.paginatedList campus=$campus college+course filter", function () use ($am, $campus) {
    $row = (array) DB::selectOne('SELECT college, course FROM alumni WHERE campus_id = ? LIMIT 1', [$campus]);
    $f = ['college' => $row['college'], 'course' => $row['course']];
    $am->allByStatusPaginated('Active', $campus, 5, 0, '', $f);
    $am->countByStatus('Active', $campus, '', $f, 10000);
});
$run('alumni.unfiltered total (summary)', fn () => \App\Services\ReportingSummary::activeUsers(null));
$run('alumni.paginatedList page=200 (no search)', function () use ($am) {
    $am->allByStatusPaginated('Active', null, 25, 5000, '');
    $am->countByStatus('Active', null, '');
});
$run("alumni.paginatedList campus=$campus deep page", function () use ($am, $campus) {
    $am->allByStatusPaginated('Active', $campus, 25, 50000, '');
    $am->countByStatus('Active', $campus, '');
});

// --- EXPLAIN every distinct statement ---
$out = ["# EXPLAIN report (".date('c').")", ''];
$rows = DB::selectOne('SELECT (SELECT COUNT(*) FROM alumni) a, (SELECT COUNT(*) FROM alumni_experience) e, (SELECT COUNT(*) FROM user) u');
$out[] = "Dataset: alumni={$rows->a}, alumni_experience={$rows->e}, user={$rows->u}; innodb_buffer_pool_size=".round(DB::selectOne('SELECT @@innodb_buffer_pool_size b')->b / 1048576).'MB';
$out[] = '';
$flagged = 0;
foreach ($captured as $c) {
    $sql = $c['sql'];
    if (!preg_match('/^\s*select/i', $sql) || str_contains($sql, 'information_schema')) {
        continue;
    }
    $bindings = $c['bindings'];
    $out[] = '## '.$c['where'].'  (ran '.$c['n'].'x, '.round($c['ms']).' ms total)';
    $out[] = '```sql';
    $out[] = preg_replace('/\s+/', ' ', $sql);
    $out[] = '```';
    try {
        $plan = array_map(fn ($r) => array_change_key_case((array) $r, CASE_LOWER), DB::select('EXPLAIN '.$sql, $bindings));
        $out[] = '| table | type | key | rows(est) | filtered | Extra |';
        $out[] = '|---|---|---|---:|---:|---|';
        foreach ($plan as $p) {
                        $warn = '';
            if (($p['type'] ?? '') === 'ALL') {
                $warn = ' **FULL TABLE SCAN**';
            } elseif (($p['type'] ?? '') === 'index') {
                $warn = ' full index scan';
            }
            $extra = (string) ($p['Extra'] ?? '');
            if (str_contains($extra, 'temporary') || str_contains($extra, 'filesort')) {
                $warn .= ' [tmp/filesort]';
            }
            $out[] = sprintf('| %s | %s%s | %s | %s | %s | %s |', $p['table'] ?? '', $p['type'] ?? '', $warn, $p['key'] ?? 'NULL', number_format((float) ($p['rows'] ?? 0)), $p['filtered'] ?? '', $extra);
            $flagged += $warn !== '' ? 1 : 0;
        }
        if (isset($o['analyze'])) {
            $t = microtime(true);
            $an = DB::select('EXPLAIN ANALYZE '.$sql, $bindings);
            $tree = (string) ((array) $an[0])[array_key_first((array) $an[0])];
            $out[] = '';
            $out[] = sprintf('EXPLAIN ANALYZE wall %.1fs — top node:', microtime(true) - $t);
            $out[] = '```';
            $out[] = implode("\n", array_slice(explode("\n", $tree), 0, 6));
            $out[] = '```';
        }
    } catch (\Throwable $e) {
        $out[] = 'EXPLAIN failed: '.$e->getMessage();
    }
    $out[] = '';
}
$out[] = "flagged plan rows (full scan / full index scan / tmp / filesort): {$flagged}";
file_put_contents($o['out'], implode("\n", $out));
echo "wrote {$o['out']} (".count($captured)." distinct statements, {$flagged} flagged)\n";
