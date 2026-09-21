import csv, sys


def col(rows, k, f=float):
    v = []
    for r in rows:
        try:
            v.append(f(r[k]))
        except (ValueError, KeyError, TypeError):
            pass
    return v


os_rows = list(csv.DictReader(open(sys.argv[1])))
db_rows = list(csv.DictReader(open(sys.argv[2])))


def st(v):
    return f"avg {sum(v)/len(v):.1f} / max {max(v):.1f}" if v else "n/a"


def s(k):
    return int(sum(col(db_rows, k)))


print("--- resources (2s samples) ---")
print("system CPU %          :", st(col(os_rows, 'sys_cpu_pct')))
print("mysqld cores busy     :", st(col(os_rows, 'mysql_cores')))
print("httpd/php cores busy  :", st(col(os_rows, 'httpd_cores')))
print("mysqld RSS MB         :", st(col(os_rows, 'mysql_ws_mb')))
print("queue-worker cores busy:", st(col(os_rows, 'worker_cores')))
print("queue-worker RSS MB (sum):", st(col(os_rows, 'worker_ws_mb')))
print("httpd/php RSS MB      :", st(col(os_rows, 'httpd_ws_mb')))
print("free RAM MB (min)     :", min(col(os_rows, 'free_ram_mb') or [0]))
print("mysql threads_running :", st(col(db_rows, 'threads_running')), "| connected max", max(col(db_rows, 'threads_connected') or [0]))
print("row-lock waits:", s('d_Innodb_row_lock_waits'), "| lock wait ms:", s('d_Innodb_row_lock_time'), "| max current waits:", max(col(db_rows, 'row_lock_current_waits') or [0]), "| deadlocks:", max(col(db_rows, 'deadlocks') or [0]))
print("longest running query (s):", max(col(db_rows, 'longest_query_s') or [0]))
print("tmp tables:", s('d_Created_tmp_tables'), "| tmp on disk:", s('d_Created_tmp_disk_tables'), "| sort merge passes:", s('d_Sort_merge_passes'))
print("full scans (Select_scan):", s('d_Select_scan'), "| Handler_read_rnd_next:", s('d_Handler_read_rnd_next'), "| Innodb_rows_read:", s('d_Innodb_rows_read'))
bp = col(db_rows, 'bp_hit_pct')
print("buffer-pool hit %     :", f"min {min(bp):.2f} avg {sum(bp)/len(bp):.2f}" if bp else "n/a", "| disk page reads:", s('d_Innodb_buffer_pool_reads'), "| fsyncs:", s('d_Innodb_data_fsyncs'))
print("rows inserted:", s('d_Innodb_rows_inserted'), "| commits:", s('d_Com_commit'), "| max_connections refusals:", s('d_Connection_errors_max_connections'))
