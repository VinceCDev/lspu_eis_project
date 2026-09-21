<?php
/**
 * Runs the REAL EmploymentReportImporter in-process and reports wall/CPU time, peak memory,
 * SQL statements per row and where the time goes (read vs write phase).
 *
 *   source tests/Performance/env.sh
 *   php tests/Performance/import_probe.php --file=C:/lspu_perf/files/t1000.xlsx --campus=7 --year=2023 [--memlimit=768M]
 */
$o = getopt('', ['file:', 'campus:', 'year::', 'memlimit::', 'dry', 'dump::', 'ext::']);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!str_contains(config('database.connections.mysql.database'), 'perf')) { fwrite(STDERR, "not a perf DB\n"); exit(1); }
ini_set('memory_limit', $o['memlimit'] ?? '768M');   // prod php.ini value; the importer raises it itself to 1024M

use Illuminate\Support\Facades\DB;
$queries = 0; $qms = 0.0; $byType = [];
DB::listen(function ($q) use (&$queries, &$qms, &$byType) {
    $queries++; $qms += $q->time;
    $k = strtoupper(strtok(ltrim($q->sql), ' '));
    $byType[$k] = ($byType[$k] ?? 0) + 1;
});
$ext = $o['ext'] ?? null; // only the patched importer accepts a client extension
$t0 = microtime(true); $c0 = getrusage(); $phase = []; $last = null;
$imp = new App\Services\EmploymentReportImporter();
try {
    $cb = function ($p) use (&$phase, $t0) { $phase[$p['phase']] ??= microtime(true) - $t0; };
    $res = isset($o['dry']) ? $imp->preview($o['file'], (int) $o['campus'], (int) ($o['year'] ?? 2023), ...($ext ? [$ext] : [])) : $imp->import($o['file'], (int) $o['campus'], (int) ($o['year'] ?? 2023), $cb, ...($ext ? [$ext] : []));
    $ok = true;
} catch (\Throwable $e) { $ok = false; $err = get_class($e).': '.$e->getMessage(); }
if (isset($o['dump']) && isset($res)) { $r = $res; unset($r['samples']); ksort($r); file_put_contents($o['dump'], json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); }
$c1 = getrusage();
$cpu = ($c1['ru_utime.tv_sec'] - $c0['ru_utime.tv_sec']) + ($c1['ru_utime.tv_usec'] - $c0['ru_utime.tv_usec']) / 1e6
     + ($c1['ru_stime.tv_sec'] - $c0['ru_stime.tv_sec']) + ($c1['ru_stime.tv_usec'] - $c0['ru_stime.tv_usec']) / 1e6;
$wall = microtime(true) - $t0;
$rows = $res['imported'] ?? 0;
echo json_encode([
    'file' => basename($o['file']), 'ok' => $ok, 'error' => $err ?? null,
    'wall_s' => round($wall, 1), 'php_cpu_s' => round($cpu, 1), 'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576),
    'phase_start_s' => array_map(fn ($v) => round($v, 1), $phase),
    'imported' => $rows, 'graduate_rows' => $res['graduate_rows'] ?? null, 'skipped' => $res['skipped'] ?? null,
    'rows_per_s' => $rows ? round($rows / $wall, 1) : 0,
    'sql_total' => $queries, 'sql_per_row' => $rows ? round($queries / $rows, 2) : null, 'sql_ms_total' => round($qms / 1000, 1),
    'sql_by_type' => $byType,
], JSON_PRETTY_PRINT), "\n";
