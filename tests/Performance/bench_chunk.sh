#!/bin/bash
# Chunk-size benchmark (500/1000/2000/5000) and write cost of the two alumni name-search indexes, one 100k workbook alone per run.
#   tests/Performance/bench_chunk.sh > C:/lspu_perf/bench_chunk.log
cd "$(dirname "$0")/../.."
source tests/Performance/env.sh
export QUEUE_CONNECTION=database
S=C:/lspu_perf
bash tests/Performance/stop_workers.sh > /dev/null           # nothing else may compete for the DB
powershell -NoProfile -Command "(Get-Process -Id $(cat /proc/$$/winpid)).ProcessorAffinity = [IntPtr]0xF0F"
powershell -NoProfile -File tests/Performance/pin_stack.ps1 -Mask 240 > /dev/null
sql() { php -r '$p=new PDO("mysql:host=127.0.0.1;port=3391;dbname=lspu_eis_perf","root","");$p->exec($argv[1]);' "$1"; }
settle() { # wait for InnoDB purge of the previous restore (history list) so runs are comparable
  for i in $(seq 1 90); do
    h=$(php -r '$p=new PDO("mysql:host=127.0.0.1;port=3391;dbname=lspu_eis_perf","root","");$r=$p->query("show engine innodb status")->fetch(PDO::FETCH_NUM);preg_match("/History list length (\d+)/",$r[2],$m);echo $m[1];')
    [ "${h:-0}" -lt 300 ] && break; sleep 5
  done; sleep 10
}
one() { # one <label> <chunk>
  php tests/Performance/db_snapshot.php snap $S/snap_bench.json > /dev/null
  IMPORT_CHUNK_SIZE=$2 php -d opcache.enable_cli=0 tests/Performance/bench_import_direct.php --file=$S/files/c1_100k.xlsx --campus=7 --label="$1"
  php tests/Performance/db_snapshot.php restore $S/snap_bench.json > /dev/null
  settle
}
echo "# chunk size (both name indexes present)"
for c in 500 1000 2000 5000; do one "chunk=$c" $c; done
echo "# name indexes: alternate without / with (chunk 1000), twice each"
for round in 1 2; do
  sql "ALTER TABLE alumni DROP INDEX alumni_last_name_first_name_index, DROP INDEX alumni_first_name_index"; settle
  one "no-name-indexes round=$round" 1000
  sql "ALTER TABLE alumni ADD INDEX alumni_last_name_first_name_index (last_name, first_name), ADD INDEX alumni_first_name_index (first_name)"; settle
  one "with-name-indexes round=$round" 1000
done
echo "# done"
