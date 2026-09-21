#!/bin/bash
# Starts the queue side of the stack on the scratch DB (same layout as docker-compose):
#   IMPORT_WORKERS x  queue:work database_long --queue=imports     (bulk imports; at most IMPORT_WORKERS run at once)
#   1 x               queue:work database --queue=summaries,default (dashboard/report summary rebuilds)
#   1 x               schedule:work                                 (dead-import recovery, stale-summary refresh)
# usage: tests/Performance/start_workers.sh [N]        stop with tests/Performance/stop_workers.sh
cd "$(dirname "$0")/../.."
source tests/Performance/env.sh
export QUEUE_CONNECTION=database
export IMPORT_WORKERS=${1:-${IMPORT_WORKERS:-2}}
mkdir -p /c/lspu_perf/workers
PHP="C:/xampp/php/php.exe"
start() { # start <logname> <args...>
  local log=$1; shift
  powershell -NoProfile -Command "Start-Process -FilePath '$PHP' -ArgumentList '-c','C:/lspu_perf/php','-d','opcache.enable_cli=0','artisan',$(printf "'%s'," "$@" | sed 's/,$//') -WorkingDirectory 'C:/xampp/htdocs/lspu_eis_laravel' -RedirectStandardOutput 'C:/lspu_perf/workers/$log.out.log' -RedirectStandardError 'C:/lspu_perf/workers/$log.err.log' -WindowStyle Hidden"
}
for i in $(seq 1 "$IMPORT_WORKERS"); do
  start "import$i" queue:work database_long --queue=imports --timeout=3600 --sleep=1 --tries=1
done
start summaries queue:work database --queue=summaries,default --timeout=1800 --sleep=1 --tries=3
start scheduler schedule:work
echo "started $IMPORT_WORKERS import worker(s) + 1 summary worker + scheduler"
