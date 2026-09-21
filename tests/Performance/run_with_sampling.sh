#!/bin/bash
# usage: run_with_sampling.sh <name> <seconds> -- <loadtest.mjs args...>
# Starts OS + DB samplers around one load-test run, then prints their peak/avg summaries.
cd "$(dirname "$0")/../.."
source tests/Performance/env.sh
NAME=$1; SECS=$2; shift 3
R=C:/lspu_perf/results; mkdir -p /c/lspu_perf/results
rm -f $R/${NAME}_os.csv.stop $R/${NAME}_db.csv.stop   # BEFORE the samplers start (a stale stop file would end them at once)
powershell -NoProfile -File tests/Performance/sampler.ps1 -Out $R/${NAME}_os.csv -Seconds $((SECS+20)) -Interval 2 -HttpdPidFile ${HTTPD_PIDFILE:-C:/lspu_perf/apache/httpd.pid} &
php tests/Performance/db_sampler.php --out=$R/${NAME}_db.csv --seconds=$((SECS+20)) --interval=2 &
sleep 3
node tests/Performance/loadtest.mjs --name "$NAME" "$@"
touch $R/${NAME}_os.csv.stop $R/${NAME}_db.csv.stop   # samplers stop when the load test ends (SECS is only an upper bound)
wait
python tests/Performance/summarize_samples.py $R/${NAME}_os.csv $R/${NAME}_db.csv
