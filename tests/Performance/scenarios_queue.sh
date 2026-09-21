#!/bin/bash
# Queue-architecture scenarios on the scratch stack (4-CPU pinned), each cold or warm:
#   tests/Performance/scenarios_queue.sh <run...>      run = <A|B|W|R0>[c|w]   (c = cold app cache, w = warm; default cold)
#     R0  10 readers, no uploads                                (reference)
#     A   10 readers + 1 x 100k upload  (campus 1)              Test A
#     B   20 readers + 6 x 100k uploads (all campuses)          Test B  (IMPORT_WORKERS=2 => 2 run, 4 wait)
#     W   50 readers (dashboard/reports/search/paging/export/location/nav) + 6 x 100k uploads   worst case
# Env: IMPORT_WORKERS (default 2), KEEP=1 keeps the imported rows (for the growth benchmark) instead of restoring the DB.
cd "$(dirname "$0")/../.."
source tests/Performance/env.sh
export QUEUE_CONNECTION=database
WORKERS=${IMPORT_WORKERS:-2}
BASE=http://127.0.0.1:8091
S=C:/lspu_perf

powershell -NoProfile -Command "(Get-Process -Id $(cat /proc/$$/winpid)).ProcessorAffinity = [IntPtr]0xF0F"   # load generator + samplers off the stack's cores

up() { local n=$1 a=""; for i in $(seq 1 $n); do a="$a --upload $i:$S/files/c${i}_100k.xlsx"; done; echo $a; }
R10="--vu dashboard:3:super --vu dashboard:1:1 --vu reports:2:super --vu reports:1:2 --vu search:2:3 --vu location:1:super --vu nav:2:4"
RNS="--vu dashboard:3:super --vu dashboard:1:1 --vu reports:2:super --vu reports:1:2 --vu location:1:super --vu nav:2:4"
R20="--vu dashboard:6:super --vu dashboard:2:1 --vu reports:4:super --vu reports:2:2 --vu search:4:3 --vu location:2:super --vu nav:4:4"
R50="--vu dashboard:12:super --vu dashboard:4:1 --vu dashboard:2:2 --vu reports:8:super --vu reports:4:2 --vu reports:2:5 --vu search:6:3 --vu alumni:4:1 --vu location:2:super --vu export:2:1 --vu nav:4:4"

for run in "$@"; do
  WARM=0; [[ $run == *w ]] && { WARM=1; run=${run%w}; }; [[ $run == *c ]] && run=${run%c}
  N=queue_${WORKERS}w_$run$([ $WARM = 1 ] && echo _warm || echo _cold)
  bash tests/Performance/settle.sh
  php tests/Performance/db_snapshot.php snap $S/snap_q_$run.json > /dev/null
  SINCE=$(php -r '$p=new PDO("mysql:host=127.0.0.1;port=3391;dbname=lspu_eis_perf","root","");echo (int)$p->query("select coalesce(max(id),0) from import_jobs")->fetchColumn();')
  bash tests/Performance/restart_workers.sh $WORKERS --clean > /dev/null       # fresh workers (no leftover state), opcache reset
  sleep 3
  powershell -NoProfile -File tests/Performance/pin_stack.ps1 -Mask 240 > /dev/null
  php tests/Performance/clear_cache.php
  if [ $WARM = 1 ]; then
    node tests/Performance/loadtest.mjs --once --timeout 300 --name _warmup --base $BASE --vu dashboard:1:super --vu dashboard:1:1 --vu reports:1:super --vu reports:1:2 --vu alumni:1:1 > /dev/null
  fi
  case $run in
    U)   ARGS="--vu nav:1:4 $(up 1)";                                         DUR=${DUR:-60} ;;   # upload only (+1 idle nav user)
    Ans) ARGS="$RNS $(up 1)";   DUR=${DUR:-60} ;;   # A without the search readers
    As)  ARGS="--vu search:4:3 --vu nav:1:4 $(up 1)"; DUR=${DUR:-60} ;;   # A with ONLY search readers
    R0) ARGS="$R10";            DUR=${DUR:-150} ;;
    A)  ARGS="$R10 $(up 1)";    DUR=${DUR:-200} ;;
    B)  ARGS="$R20 $(up 6)";    DUR=${DUR:-560} ;;
    W)  ARGS="$R50 $(up 6)";    DUR=${DUR:-560} ;;
  esac
  echo; echo "################ $N  ($(date +%T))  workers=$WORKERS"
  tests/Performance/run_with_sampling.sh $N $((DUR + ${TAIL:-30})) -- --base $BASE --duration $DUR --think 1500 --timeout ${UPTIMEOUT:-3600} --until-uploads --wait-uploads $ARGS
  php tests/Performance/wait_summaries.php --since=$SINCE --timeout=300 | tee $S/results/${N}_imports.txt
  php tests/Performance/db_snapshot.php stats | tee -a $S/results/${N}_imports.txt | grep -E "alumni |user "
  if [ -z "${KEEP:-}" ]; then
    echo "--- restoring dataset"; php tests/Performance/db_snapshot.php restore $S/snap_q_$run.json | grep removed | tr '\n' ' '; echo
    php artisan reporting:rebuild --sync > /dev/null
  fi
done
