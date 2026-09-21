<?php
/**
 * Controlled A/B test of candidate indexes on the SAME data in the SAME session: MySQL 8 "invisible" indexes let the optimizer
 * ignore an index without dropping it, so each state is measured back to back (buffer pool, machine noise and data identical).
 *
 *   source tests/Performance/env.sh
 *   php tests/Performance/index_ab.php --rounds=2 --out=tests/Performance/results/index_ab.md
 *
 * States:  S0 = every candidate invisible (== schema.sql as deployed by docker/entrypoint.sh)
 *          S1 = + the 2026_09_09 "reporting" indexes
 *          S2 = + the new covering indexes (2026_09_21 migration)
 * Each state runs the real Dashboard / Report / search code paths; per-statement time (min of N rounds) comes from DB::listen.
 */
$o = getopt('', ['rounds::', 'out:']);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!str_contains(config('database.connections.mysql.database'), 'perf')) {
    exit("not a perf DB\n");
}
ini_set('memory_limit', '4G');
set_time_limit(0);

use App\Models\Alumni;
use App\Models\DashboardStats;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;

$rounds = (int) ($o['rounds'] ?? 2);
$groups = [
    'S1' => [ // 2026_09_09 as actually created by Laravel (auto names)
        ['alumni', 'alumni_year_graduated_index'], ['alumni', 'alumni_course_college_index'], ['alumni', 'alumni_campus_id_year_graduated_index'],
        ['alumni', 'alumni_city_province_course_index'], ['alumni_experience', 'alumni_experience_alumni_id_current_index'], ['alumni_experience', 'alumni_experience_employment_status_index'],
    ],
    'S2' => [ // new covering candidates
        ['alumni', 'alumni_campus_course_college_index'], ['alumni_experience', 'alumni_experience_alumni_current_end_index'], ['alumni_experience', 'alumni_experience_current_cover_index'],
    ],
];
$create = [
    'alumni_campus_course_college_index' => ['alumni', 'campus_id, course, college'],
    'alumni_experience_alumni_current_end_index' => ['alumni_experience', 'alumni_id, current, end_date'],
    'alumni_experience_current_cover_index' => ['alumni_experience', 'current, alumni_id, location_of_work, employment_sector, employment_status'],
];
$have = fn (string $t, string $i) => DB::table('information_schema.statistics')->where('table_schema', DB::connection()->getDatabaseName())->where('table_name', $t)->where('index_name', $i)->exists();
foreach ($create as $name => [$t, $cols]) {
    if (!$have($t, $name)) {
        $t0 = microtime(true);
        DB::statement("ALTER TABLE `$t` ADD INDEX `$name` ($cols)");
        fwrite(STDERR, sprintf("created %s in %.0fs\n", $name, microtime(true) - $t0));
    }
}
$vis = function (array $list, bool $visible) {
    foreach ($list as [$t, $i]) {
        DB::statement("ALTER TABLE `$t` ALTER INDEX `$i` ".($visible ? 'VISIBLE' : 'INVISIBLE'));
    }
};

$workload = function (): void {
    $c = 8;
    $ds = new DashboardStats();
    $ds->chartBreakdowns();
    $ds->alumniMap();
    $ids = array_map(fn ($x) => (int) $x['campus_id'], (new App\Models\Campus())->all());
    $ds->employmentStatusPerProgramByCampus($ids);
    $dc = new DashboardStats($c);
    $dc->chartBreakdowns();
    $dc->alumniMap();
    $dc->currentCourseJobTitles();
    $ds->alumniAtLocation('San Pablo City', 'Laguna', 20, 0);
    (new ReportService(null, null, null, null, null))->summary();
    (new ReportService(null, null, $c, null, null))->summary();
    (new ReportService(null, null, null, null, 2020))->summary();
    (new Report(null))->distinctYears();
    (new Alumni())->allByStatusPaginated('Active', $c, 25, 50000, '');
    (new Alumni())->countByStatus('Active', $c, '');
};

$res = [];   // state => sqlkey => [min ms, label]
foreach (['S0' => [], 'S1' => ['S1'], 'S2' => ['S1', 'S2']] as $state => $on) {
    foreach ($groups as $g => $list) {
        $vis($list, in_array($g, $on, true));
    }
    for ($r = 0; $r < $rounds; $r++) {
        $seen = [];
        $h = function ($q) use (&$seen) {
            $k = md5($q->sql.json_encode($q->bindings));
            $seen[$k] = ['ms' => $q->time, 'sql' => preg_replace('/\s+/', ' ', $q->sql)];
        };
        DB::listen($h);
        $t0 = microtime(true);
        $workload();
        fwrite(STDERR, sprintf("state %s round %d total %.1fs\n", $state, $r + 1, microtime(true) - $t0));
        foreach ($seen as $k => $v) {
            $res[$state][$k] = ['ms' => min($res[$state][$k]['ms'] ?? INF, $v['ms']), 'sql' => $v['sql']];
        }
        // (DB::listen callbacks accumulate; only the latest closure writes to $seen of its own round.)
    }
}
foreach ($groups as $list) {
    $vis($list, true);
}

$out = ["# Index A/B (same session, invisible-index toggle; min of {$rounds} rounds)", ''];
$tot = ['S0' => 0, 'S1' => 0, 'S2' => 0];
$rows = [];
foreach ($res['S0'] as $k => $v) {
    if ($v['ms'] < 150 && ($res['S1'][$k]['ms'] ?? 0) < 150) {
        continue;
    }
    $rows[] = [$v['ms'], $res['S1'][$k]['ms'] ?? null, $res['S2'][$k]['ms'] ?? null, substr($v['sql'], 0, 150)];
}
usort($rows, fn ($a, $b) => $b[0] <=> $a[0]);
$out[] = '| S0 no idx | S1 +09_09 | S2 +covering | statement |';
$out[] = '|---:|---:|---:|---|';
foreach ($rows as [$a, $b, $c, $sql]) {
    $out[] = sprintf('| %.0f ms | %.0f ms | %.0f ms | %s |', $a, $b, $c, str_replace('|', '\\|', $sql));
}
foreach ($res as $st => $m) {
    $tot[$st] = array_sum(array_column($m, 'ms'));
}
$out[] = '';
$out[] = sprintf('**Sum of all statement times:** S0 %.1fs, S1 %.1fs, S2 %.1fs', $tot['S0'] / 1000, $tot['S1'] / 1000, $tot['S2'] / 1000);
file_put_contents($o['out'], implode("\n", $out));
echo implode("\n", array_slice($out, -1))."\n";
