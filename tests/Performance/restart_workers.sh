#!/bin/bash
# After editing code: reload the queue workers (they keep the app in memory) and the shared opcache; optionally empty the queue.
#   tests/Performance/restart_workers.sh [N] [--clean]     --clean also empties import_jobs / queue_jobs / failed_jobs
cd "$(dirname "$0")/../.."
source tests/Performance/env.sh
export QUEUE_CONNECTION=database
N=${1:-2}; [ "$N" = "--clean" ] && N=2
bash tests/Performance/stop_workers.sh > /dev/null
curl -s http://127.0.0.1:8091/__opcache_reset > /dev/null
if [[ " $* " == *" --clean "* ]]; then
  php -r '$p=new PDO("mysql:host=127.0.0.1;port=3391;dbname=lspu_eis_perf","root","");foreach(["import_jobs","queue_jobs","failed_jobs"] as $t)$p->exec("truncate table $t");$p->exec("delete from cache where `key` like \"%import%\" or `key` like \"%rpt:%\"");echo "queue tables emptied\n";'
  rm -f storage/app/private/imports/* 2>/dev/null
fi
bash tests/Performance/start_workers.sh "$N"
