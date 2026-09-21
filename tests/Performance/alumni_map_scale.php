<?php

/**
 * Measures the Alumni Location map's server side against a (synthetic, scratch) database. Every figure printed is a
 * measurement from THIS run on THIS machine - nothing is estimated.
 *
 *   DB_DATABASE=lspu_eis_perf CACHE_STORE=array php tests/Performance/alumni_map_scale.php [--summary]
 *
 * Seed the database first with tests/Performance/seed_alumni_map_scale.php. With --summary the reporting summary tables
 * are rebuilt first (as production does after imports) so the summary path is measured too; without it the live
 * GROUP BY path is measured.
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DashboardStats;
use App\Services\AlumniLocationViewer;
use App\Services\AlumniMapService;
use App\Services\ReportingSummary;
use Illuminate\Support\Facades\DB;

$db = DB::connection()->getDatabaseName();
if ($db === 'lspu_eis') {
    fwrite(STDERR, "Refusing to run against the real database.\n");
    exit(1);
}
$useSummary = in_array('--summary', $argv, true);

function ms(callable $f, &$out = null): float
{
    $t = microtime(true);
    $out = $f();

    return round((microtime(true) - $t) * 1000, 1);
}
function row(string $label, $value, string $unit = 'ms'): void
{
    printf("  %-58s %12s %s\n", $label, is_float($value) ? number_format($value, 1) : $value, $unit);
}

$alumni = (int) DB::table('alumni')->count();
echo "Database: {$db}   alumni rows: ".number_format($alumni).'   '.($useSummary ? 'SUMMARY tables' : 'LIVE queries')."\n\n";

if ($useSummary) {
    echo "Rebuilding summary tables synchronously (timed separately; NOT part of a map request)...
";
    $t = microtime(true);
    passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../../artisan').' reporting:rebuild --sync');
    printf("  rebuilt in %.1f s

", microtime(true) - $t);
    ReportingSummary::flushMemo();
    printf("  summary ready(all campuses) = %s

", var_export(ReportingSummary::ready(null), true));
}

echo "1. Building the per-location summaries (what the old `stats` payload did on every cold cache)\n";
$clusters = [];
row('DashboardStats::alumniMap()', ms(fn () => (new DashboardStats(null))->alumniMap(), $clusters));
row('distinct locations (pins + unmapped)', count($clusters), '');
row('json size of that map, as the old stats payload carried it', round(strlen(json_encode($clusters)) / 1048576, 2), 'MB');

row('peak PHP memory so far', round(memory_get_peak_usage(true) / 1048576), 'MB');

echo "\n2. New: cached dataset joined with coordinates, then sliced per request\n";
$svc = new AlumniMapService();
$ds = [];
row('dataset() cold (build + cache write)', ms(fn () => $svc->dataset(null), $ds));
row('dataset() warm (cache hit + unserialize)', ms(fn () => $svc->dataset(null), $ds));
row('mapped locations (have coordinates)', count($ds['locations']), '');
row('unmapped locations / alumni', $ds['unmapped']['locations'].' / '.number_format($ds['unmapped']['alumni']), '');
$slice = [];
row('slice() whole country (no bounds)', ms(fn () => AlumniMapService::slice($ds, null, 6), $slice));
row('   -> mode / rows returned', $slice['mode'].' / '.count($slice['locations']), '');
row('   -> json payload', round(strlen(json_encode($slice)) / 1024, 1), 'KB');
$bbox = ['north' => 14.7, 'south' => 13.6, 'east' => 121.9, 'west' => 120.7];
row('slice() Laguna-sized viewport', ms(fn () => AlumniMapService::slice($ds, $bbox, 10), $slice));
row('   -> mode / rows returned', $slice['mode'].' / '.count($slice['locations']), '');
row('   -> json payload', round(strlen(json_encode($slice)) / 1024, 1), 'KB');

// Data-correctness check: the lean summary-path build must equal the live GROUP BY build, location for location.
if (ReportingSummary::ready(null)) {
    $svcR = new ReflectionClass(AlumniMapService::class);
    $call = static function (string $m) use ($svcR) {
        $meth = $svcR->getMethod($m);
        $meth->setAccessible(true);

        return $meth->invoke(new AlumniMapService(), null);
    };
    $sumB = [];
    $liveB = [];
    $before = memory_get_peak_usage(true);
    row('build from SUMMARY tables (the production dataset build)', ms(fn () => $call('buildFromSummary'), $sumB));
    row('   peak PHP memory after it', round(memory_get_peak_usage(true) / 1048576), 'MB');
    row('build from LIVE query (fallback: no summary tables)', ms(fn () => $call('buildLive'), $liveB));
    $norm = static function (array $d): array {
        $o = [];
        foreach ($d['locations'] as $l) {
            $o[mb_strtolower($l['city'].'|'.$l['province'])] = [$l['count'], $l['employed'], $l['lat'], $l['lng'], array_map(static fn ($c) => $c['count'], $l['top_courses'])];
        }
        ksort($o);

        return [$o, $d['unmapped']];
    };
    [$a, $ua] = $norm($sumB);
    [$b, $ub] = $norm($liveB);
    foreach ($a as $k => $v) {        // equal-count courses may be listed in either order: compare the counts only
        rsort($a[$k][4]);
        if (isset($b[$k])) {
            rsort($b[$k][4]);
        }
    }
    echo '  summary == live?  locations: '.($a === $b ? 'IDENTICAL' : 'DIFFERENT').' ('.count($a).' vs '.count($b).')'
        .'   unmapped: '.($ua === $ub ? 'IDENTICAL' : 'DIFFERENT '.json_encode([$ua, $ub]))."\n";
}

echo "\n3. Aggregation safety valve: synthetic location lists\n";
foreach ([5000, 20000, 100000] as $n) {
    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $pts[] = ['city' => "C{$i}", 'province' => 'P', 'lat' => 6 + mt_rand(0, 14000) / 1000, 'lng' => 117 + mt_rand(0, 9000) / 1000, 'count' => mt_rand(1, 500), 'employed' => 1, 'top_courses' => []];
    }
    $out = [];
    row("slice() of {$n} locations at zoom 6", ms(fn () => AlumniMapService::slice(['locations' => $pts, 'unmapped' => ['locations' => 0, 'alumni' => 0]], null, 6), $out));
    row('   -> mode / cells / json', $out['mode'].' / '.count($out['locations']).' / '.round(strlen(json_encode($out)) / 1024).' KB', '');
}

echo "
4. Single-alumnus viewer: the ordered list is built ONCE per location (cold), then every Next/Previous is O(1)
";
$viewer = new AlumniLocationViewer();
$big = DB::selectOne('SELECT city, province, COUNT(*) n FROM alumni WHERE city NOT LIKE ? GROUP BY city, province ORDER BY n DESC LIMIT 1', ['Brgy.%']);
$small = DB::selectOne('SELECT city, province, COUNT(*) n FROM alumni WHERE city NOT LIKE ? GROUP BY city, province ORDER BY ABS(COUNT(*) - 100) LIMIT 1', ['Brgy.%']);
foreach ([['biggest location', $big], ['~100-alumni location', $small]] as [$name, $loc]) {
    if (!$loc) {
        continue;
    }
    $n = (int) $loc->n;
    echo "  {$name}: {$loc->city}, {$loc->province}  ({$n} alumni)
";
    $r = [];
    row('   first click (cold: sorts + caches the list, +1 lookup)', ms(fn () => $viewer->at(null, $loc->city, $loc->province, null, 0), $r));
    foreach ([['Next (index 1)', 1], ['middle', intdiv($n, 2)], ['last', $n - 1]] as [$label, $idx]) {
        $best = INF;
        $worst = 0.0;
        for ($k = 0; $k < 20; $k++) {
            $t = ms(fn () => $viewer->at(null, $loc->city, $loc->province, null, $idx), $r);
            $best = min($best, $t);
            $worst = max($worst, $t);
        }
        row("   {$label} - best / worst of 20", number_format($best, 1).' / '.number_format($worst, 1));
    }
}
echo "
  For comparison, the OLD per-click cost (ORDER BY ... LIMIT 1 OFFSET n on the biggest location):
";
foreach ([0, 40000, 76000] as $off) {
    $best = INF;
    for ($k = 0; $k < 3; $k++) {
        $best = min($best, ms(fn () => DB::select('SELECT a.alumni_id FROM alumni a WHERE a.city = ? AND a.province = ? ORDER BY a.last_name, a.first_name, a.alumni_id LIMIT 1 OFFSET '.$off, [$big->city, $big->province])));
    }
    row("   OFFSET {$off} - best of 3", $best);
}
echo "  EXPLAIN of that query:
";
foreach (DB::select('EXPLAIN SELECT a.alumni_id FROM alumni a WHERE a.city = ? AND a.province = ? ORDER BY a.last_name, a.first_name, a.alumni_id LIMIT 1 OFFSET 40000', [$big->city, $big->province]) as $e) {
    echo '   '.json_encode($e)."
";
}
