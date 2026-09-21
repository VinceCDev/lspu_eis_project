<?php
/**
 * Imports one workbook in-process through the queue job's code path (chunked, checkpointing every chunk) and prints one JSON line:
 * rows/s, peak memory, chunk transaction times. Used for the chunk-size and index-cost benchmarks. Perf DB only.
 *   IMPORT_CHUNK_SIZE=1000 php -d opcache.enable_cli=0 tests/Performance/bench_import_direct.php --file=C:/lspu_perf/files/c1_100k.xlsx --campus=7
 */
$o = getopt('', ['file:', 'campus:', 'year::', 'label::']);
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!str_contains(config('database.connections.mysql.database'), 'perf')) {
    fwrite(STDERR, "not a perf DB\n");
    exit(1);
}
ini_set('memory_limit', '768M');

use Illuminate\Support\Facades\DB;

$status = function (): array {
    $out = [];
    foreach (DB::select("SHOW GLOBAL STATUS WHERE Variable_name IN ('Innodb_os_log_written','Innodb_data_written','Innodb_rows_inserted','Innodb_buffer_pool_pages_flushed','Innodb_log_waits')") as $r) {
        $out[$r->Variable_name] = (int) $r->Value;
    }

    return $out;
};
$s0 = $status();
$q = 0;
DB::listen(function () use (&$q) { ++$q; });
$chunks = [];
$last = $t0 = microtime(true);
$c0 = getrusage();
$imp = new App\Services\EmploymentReportImporter();
$res = $imp->import($o['file'], (int) $o['campus'], (int) ($o['year'] ?? 2023), null, 'xlsx', null, function (array $st) use (&$chunks, &$last) {
    $now = microtime(true);
    $chunks[] = ($now - $last) * 1000;
    $last = $now;
});
$wall = microtime(true) - $t0;
$c1 = getrusage();
$cpu = ($c1['ru_utime.tv_sec'] - $c0['ru_utime.tv_sec']) + ($c1['ru_utime.tv_usec'] - $c0['ru_utime.tv_usec']) / 1e6
    + ($c1['ru_stime.tv_sec'] - $c0['ru_stime.tv_sec']) + ($c1['ru_stime.tv_usec'] - $c0['ru_stime.tv_usec']) / 1e6;
$s1 = $status();
sort($chunks);
$p = fn (float $f) => round($chunks[(int) min(count($chunks) - 1, floor($f * count($chunks)))] ?? 0);
echo json_encode([
    'label' => $o['label'] ?? '', 'chunk_size' => config('import.chunk_size'), 'rows' => $res['imported'], 'skipped' => $res['skipped'],
    'wall_s' => round($wall, 1), 'rows_per_s' => (int) round($res['imported'] / $wall), 'php_cpu_s' => round($cpu, 1),
    'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576), 'chunks' => count($chunks),
    'redo_mb' => round(($s1['Innodb_os_log_written'] - $s0['Innodb_os_log_written']) / 1048576), 'data_written_mb' => round(($s1['Innodb_data_written'] - $s0['Innodb_data_written']) / 1048576),
    'rows_inserted' => $s1['Innodb_rows_inserted'] - $s0['Innodb_rows_inserted'], 'log_waits' => $s1['Innodb_log_waits'] - $s0['Innodb_log_waits'],
    'chunk_ms_p50' => $p(0.5), 'chunk_ms_p95' => $p(0.95), 'chunk_ms_max' => round(end($chunks) ?: 0), 'statements' => $q,
]), "\n";
