<?php
/**
 * MySQL-side sampler. Every N seconds records deltas of the counters that reveal scans / temp tables / lock waits /
 * buffer-pool misses, plus current threads and the longest running statement.
 *   php tests/Performance/db_sampler.php --out=C:/lspu_perf/results/x_db.csv --seconds=120 [--interval=2] [--port=3391]
 */
$o = getopt('', ['out:', 'seconds::', 'interval::', 'port::']);
$pdo = new PDO('mysql:host=127.0.0.1;port='.($o['port'] ?? 3391).';dbname=lspu_eis_perf', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$want = ['Com_select', 'Com_insert', 'Com_commit', 'Handler_read_rnd_next', 'Handler_read_first', 'Select_scan', 'Select_full_join', 'Sort_rows', 'Sort_merge_passes',
    'Created_tmp_tables', 'Created_tmp_disk_tables', 'Innodb_row_lock_waits', 'Innodb_row_lock_time', 'Innodb_buffer_pool_reads', 'Innodb_buffer_pool_read_requests',
    'Innodb_data_fsyncs', 'Innodb_rows_read', 'Innodb_rows_inserted', 'Innodb_log_waits', 'Slow_queries', 'Aborted_connects', 'Connection_errors_max_connections'];
$fh = fopen($o['out'], 'w');
fputcsv($fh, array_merge(['t', 'threads_running', 'threads_connected', 'row_lock_current_waits', 'deadlocks', 'longest_query_s', 'bp_hit_pct'], array_map(fn ($w) => "d_$w", $want)));
$prev = null;
$t0 = time();
$end = $t0 + (int) ($o['seconds'] ?? 60);
$iv = (int) ($o['interval'] ?? 2);
while (time() < $end && !file_exists($o["out"].".stop")) {
    $st = [];
    foreach ($pdo->query('SHOW GLOBAL STATUS')->fetchAll(PDO::FETCH_NUM) as [$k, $v]) {
        $st[$k] = $v;
    }
    $dead = (int) $pdo->query("SELECT COALESCE(SUM(count),0) FROM information_schema.innodb_metrics WHERE name='lock_deadlocks'")->fetchColumn();
    $lq = (float) $pdo->query("SELECT COALESCE(MAX(time),0) FROM information_schema.processlist WHERE command='Query' AND id<>CONNECTION_ID()")->fetchColumn();
    $row = [time() - $t0, $st['Threads_running'], $st['Threads_connected'], $st['Innodb_row_lock_current_waits'], $dead, $lq];
    $d = fn ($k) => $prev ? (int) $st[$k] - (int) $prev[$k] : 0;
    $req = $d('Innodb_buffer_pool_read_requests');
    $miss = $d('Innodb_buffer_pool_reads');
    $row[] = $req > 0 ? round(100 * (1 - $miss / $req), 2) : '';
    foreach ($want as $w) {
        $row[] = $d($w);
    }
    if ($prev) {
        fputcsv($fh, $row);
        fflush($fh);
    }
    $prev = $st;
    sleep($iv);
}
