#!/bin/bash
# Write-amplification of the alumni name indexes (bytes written are deterministic; wall time on this laptop is bimodal) and the effect of
# innodb_redo_log_capacity (MySQL 8.0's default is 100 MB; the scratch server runs 1 GB). One 100k workbook alone per run, chunk 1000.
#   tests/Performance/bench_redo.sh > C:/lspu_perf/bench_redo.log
cd "$(dirname "$0")/../.."
source tests/Performance/env.sh
export QUEUE_CONNECTION=database
S=C:/lspu_perf
bash tests/Performance/stop_workers.sh > /dev/null
powershell -NoProfile -Command "(Get-Process -Id $(cat /proc/$$/winpid)).ProcessorAffinity = [IntPtr]0xF0F"
powershell -NoProfile -File tests/Performance/pin_stack.ps1 -Mask 240 > /dev/null
sql() { php -r '$p=new PDO("mysql:host=127.0.0.1;port=3391;dbname=lspu_eis_perf","root","");$p->exec($argv[1]);' "$1"; }
# settle: purge done AND few dirty pages AND checkpoint age small (the previous restore's DELETE leaves ~300 MB of redo to checkpoint)
settle() {
  for i in $(seq 1 120); do
    r=$(php -r '$p=new PDO("mysql:host=127.0.0.1;port=3391;dbname=lspu_eis_perf","root","");$st=$p->query("show engine innodb status")->fetch(PDO::FETCH_NUM)[2];preg_match("/History list length (\d+)/",$st,$h);preg_match("/Log sequence number\s+(\d+)/",$st,$l);preg_match("/Last checkpoint at\s+(\d+)/",$st,$c);$d=$p->query("show global status like \"Innodb_buffer_pool_pages_dirty\"")->fetch(PDO::FETCH_NUM)[1];echo ((int)$h[1]<300 && ($l[1]-$c[1])<40*1048576 && (int)$d<3000)?"ok":"wait";')
    [ "$r" = "ok" ] && break; sleep 5
  done; sleep 5
}
one() { # one <label>
  settle
  php tests/Performance/db_snapshot.php snap $S/snap_bench.json > /dev/null
  IMPORT_CHUNK_SIZE=1000 php -d opcache.enable_cli=0 tests/Performance/bench_import_direct.php --file=$S/files/c1_100k.xlsx --campus=7 --label="$1"
  php tests/Performance/db_snapshot.php restore $S/snap_bench.json > /dev/null
}
echo "# redo capacity 1 GB, both name indexes present"
sql "SET GLOBAL innodb_redo_log_capacity=1073741824"
one "redo=1G with-name-indexes A"
one "redo=1G with-name-indexes B"
echo "# redo capacity 1 GB, name indexes dropped"
sql "ALTER TABLE alumni DROP INDEX alumni_last_name_first_name_index, DROP INDEX alumni_first_name_index"
one "redo=1G no-name-indexes A"
one "redo=1G no-name-indexes B"
sql "ALTER TABLE alumni ADD INDEX alumni_last_name_first_name_index (last_name, first_name), ADD INDEX alumni_first_name_index (first_name)"
echo "# redo capacity 100 MB (the MySQL 8.0 default), name indexes present"
sql "SET GLOBAL innodb_redo_log_capacity=104857600"
one "redo=100M with-name-indexes A"
one "redo=100M with-name-indexes B"
sql "SET GLOBAL innodb_redo_log_capacity=1073741824"
echo "# done"
