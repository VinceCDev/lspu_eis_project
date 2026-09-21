<?php
/**
 * Correctness proof for the summary tables: runs every Dashboard / Report figure twice - once from the rpt_* summary tables and
 * once from the ORIGINAL live GROUP BY queries (reporting.use_summary=false) - over many scopes, and diffs the results.
 * Also records how long each path took.
 *
 *   source tests/Performance/env.sh
 *   php tests/Performance/compare_summary_vs_live.php [--scopes=quick|full] [--out=tests/Performance/results/summary_equivalence.md]
 *
 * "quick": all campuses + one campus (+ college / year filters). "full": every campus, every college of it, three years.
 * Rows whose order the live SQL leaves unspecified (ties in ORDER BY) are compared as sorted sets.
 */
$o = getopt('', ['scopes::', 'out::']);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!str_contains(config('database.connections.mysql.database'), 'perf')) {
    fwrite(STDERR, "not a perf DB\n");
    exit(1);
}
ini_set('memory_limit', '4G');
set_time_limit(0);

use App\Models\Campus;
use App\Models\DashboardStats;
use App\Models\Report;
use App\Services\AlignmentService;
use App\Services\ReportService;
use App\Services\ReportingSummary;
use Illuminate\Support\Facades\DB;

$mode = $o['scopes'] ?? 'quick';
$campuses = array_map(static fn ($c) => (int) $c['campus_id'], (new Campus())->all());
$first = $campuses[0];

// scopes: [campus|null, college|null, year|null]
$scopes = [[null, null, null], [$first, null, null]];
$colleges = DB::table('rpt_program')->where('campus_id', $first)->distinct()->orderBy('college')->pluck('college')->all();
$years = DB::table('rpt_program')->where('campus_id', $first)->where('year_graduated', '>', 0)->distinct()->orderByDesc('year_graduated')->pluck('year_graduated')->all();
$scopes[] = [$first, $colleges[0] ?? null, null];
$scopes[] = [$first, null, (int) ($years[0] ?? 2023)];
$scopes[] = [null, $colleges[0] ?? null, (int) ($years[1] ?? 2022)];
if ($mode === 'full') {
    foreach (array_slice($campuses, 1) as $c) {
        $scopes[] = [$c, null, null];
        foreach (DB::table('rpt_program')->where('campus_id', $c)->distinct()->pluck('college') as $col) {
            $scopes[] = [$c, $col, null];
        }
        $scopes[] = [$c, null, (int) ($years[2] ?? 2021)];
    }
}

/** figures for one scope: everything the Dashboard + Reports controllers can return */
$figures = function (?int $campus, ?string $college, ?int $year) use ($campuses) {
    $out = [];
    $ds = new DashboardStats($campus, $college);
    if ($year === null) {
        $out['dash.chartBreakdowns'] = $ds->chartBreakdowns();
        $out['dash.totalsForCards'] = $ds->totalsForCards();
        $out['dash.alumniMap'] = $ds->alumniMap();
        $out['dash.titleCounts'] = (new AlignmentService())->classify((new DashboardStats($campus, $college))->currentCourseJobTitleCounts());
        $out['dash.statusPerProgram'] = (new DashboardStats($campus, $college))->employmentStatusPerProgram();
        if ($campus === null && $college === null) {
            $out['dash.byCampus'] = (new DashboardStats())->employmentStatusPerProgramByCampus($campuses);
            $out['dash.collegesByCampus'] = (new DashboardStats())->collegesByCampus($campuses);
        }
    }
    $rs = new ReportService(null, null, $campus, $college, $year);
    $out['report.summary'] = $rs->summary();
    $r = new Report($campus, $college, $year);
    $out['report.colleges'] = (new Report($campus))->distinctColleges();
    $out['report.years'] = (new Report($campus))->distinctYears();
    $out['report.alumniCount'] = $r->alumniCount();

    return $out;
};

/** canonical form: sort every list of rows by its JSON so ties/undefined SQL order don't count as differences */
$canon = function ($v) use (&$canon) {
    if (!is_array($v)) {
        return is_numeric($v) && !is_string($v) ? $v + 0 : $v;
    }
    $isList = array_is_list($v);
    $v = array_map($canon, $v);
    if ($isList) {
        usort($v, static fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
    } else {
        ksort($v);
    }

    return $v;
};

$rows = [];
$total = 0;
$bad = 0;
foreach ($scopes as [$campus, $college, $year]) {
    $label = sprintf('campus=%s college=%s year=%s', $campus ?? 'ALL', $college ?? '-', $year ?? '-');
    ReportingSummary::flushMemo();

    config(['reporting.use_summary' => true]);
    $t = microtime(true);
    $a = $figures($campus, $college, $year);
    $tSummary = microtime(true) - $t;

    config(['reporting.use_summary' => false]);
    ReportingSummary::flushMemo();
    $t = microtime(true);
    $b = $figures($campus, $college, $year);
    $tLive = microtime(true) - $t;

    $scopeBad = 0;
    foreach ($a as $name => $va) {
        ++$total;
        $same = json_encode($canon($va)) === json_encode($canon($b[$name]));
        if (!$same) {
            ++$bad;
            ++$scopeBad;
            $ja = json_encode($canon($va));
            $jb = json_encode($canon($b[$name]));
            $i = 0;
            while ($i < strlen($ja) && $i < strlen($jb) && $ja[$i] === $jb[$i]) {
                ++$i;
            }
            $rows[] = "DIFF  {$label}  {$name}\n      summary: ...".substr($ja, max(0, $i - 60), 160)."\n      live:    ...".substr($jb, max(0, $i - 60), 160);
        }
    }
    $rows[] = sprintf('%-52s figures=%d  summary=%6.2fs  live=%7.2fs  %s', $label, count($a), $tSummary, $tLive, $scopeBad ? "DIFFERS ({$scopeBad})" : 'identical');
    fwrite(STDERR, end($rows)."\n");
}

$summary = "compared {$total} figures over ".count($scopes)." scopes: ".($bad === 0 ? 'ALL IDENTICAL' : "{$bad} DIFFERENCES");
$text = "# Summary tables vs original live queries\n\n".$summary."\n\n```\n".implode("\n", $rows)."\n```\n";
if (!empty($o['out'])) {
    file_put_contents($o['out'], $text);
}
echo $text;
exit($bad === 0 ? 0 : 1);
