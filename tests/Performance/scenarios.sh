#!/bin/bash
# Concurrency scenarios (S0-S5) against either app build, with the server stack pinned to 4 logical CPUs (== the 4-vCPU prod VPS).
#   tests/Performance/scenarios.sh before|after <run...>      runs: R0 R1 R3 R6 U6 U1x100k W6
#     R0   10 readers only                                   (reference)
#     R1   10 readers + 1 upload   (campus 1)                (Scenario 1 / 4)
#     R3   10 readers + 3 uploads                            (Scenario 2 partial)
#     R6   10 readers + 6 uploads  (all campuses)            (Scenario 2 / 4)
#     W6   20 readers + 6 uploads                            (Scenario 5, worst case)
#     U6   6 uploads only                                    (resource picture without readers)
#     U1x100k  one 100,000-row file                          (Scenario 1 upload itself)
# Env: DUR (seconds per run, default 300), FILES=50k|100k (workbook size for R*/W6/U6, default 50k for `before`, 100k for `after`)
cd "$(dirname "$0")/../.."
APP=$1; shift
source tests/Performance/env.sh
if [ "$APP" = after ]; then BASE=http://127.0.0.1:8092; export APP_URL=$BASE; export HTTPD_PIDFILE=C:/lspu_perf/apache/httpd_after.pid; FILES=${FILES:-100k}; else BASE=http://127.0.0.1:8091; FILES=${FILES:-50k}; fi
DUR=${DUR:-300}
UPFLAGS=""; [ "$APP" = after ] && UPFLAGS="--wait-uploads --timeout 900"   # optimised build finishes imports quickly: wait and report them
powershell -NoProfile -File tests/Performance/pin_stack.ps1 -Mask 240 > /dev/null
# load generator + samplers on the other cores (children inherit this affinity)
powershell -NoProfile -Command "(Get-Process -Id $(cat /proc/$$/winpid)).ProcessorAffinity = [IntPtr]0xF0F"

up() { # up <n> -> --upload args for campuses 1..n
  local n=$1 a=""; for i in $(seq 1 $n); do
    if [ "$FILES" = 100k ]; then f=C:/lspu_perf/files/c${i}_100k.xlsx; else f=C:/lspu_perf/files/d${i}_50k.xlsx; fi
    a="$a --upload $i:$f"; done; echo $a; }
READERS10="--vu dashboard:3:super --vu dashboard:1:1 --vu reports:2:super --vu reports:1:2 --vu search:2:3 --vu location:1:super --vu nav:2:4"
READERS20="--vu dashboard:6:super --vu dashboard:2:1 --vu reports:4:super --vu reports:2:2 --vu search:4:3 --vu location:2:super --vu nav:4:4"

for run in "$@"; do
  WARM=0; [[ $run == *w ]] && { WARM=1; run=${run%w}; }
  php tests/Performance/db_snapshot.php snap C:/lspu_perf/snap_$run.json > /dev/null
  N=${APP}_${FILES}_$run$([ $WARM = 1 ] && echo w)
  if [ $WARM = 1 ]; then  # steady state: every payload the readers use was built by THIS build (own key/TTL semantics) just before the run
    php tests/Performance/clear_cache.php
    node tests/Performance/loadtest.mjs --once --timeout 900 --name _warmup --base $BASE --vu dashboard:1:super --vu dashboard:1:1 --vu reports:1:super --vu reports:1:2 > /dev/null
  else php tests/Performance/clear_cache.php; fi
  echo; echo "################ $N  ($(date +%T))"
  case $run in
    R0) tests/Performance/run_with_sampling.sh $N $DUR -- --base $BASE --duration $DUR --think 1500 $READERS10 ;;
    R1) tests/Performance/run_with_sampling.sh $N $DUR -- --base $BASE $UPFLAGS --duration $DUR --think 1500 $READERS10 $(up 1) ;;
    R3) tests/Performance/run_with_sampling.sh $N $DUR -- --base $BASE $UPFLAGS --duration $DUR --think 1500 $READERS10 $(up 3) ;;
    R6) tests/Performance/run_with_sampling.sh $N $DUR -- --base $BASE $UPFLAGS --duration $DUR --think 1500 $READERS10 $(up 6) ;;
    W6) tests/Performance/run_with_sampling.sh $N $DUR -- --base $BASE $UPFLAGS --duration $DUR --think 1500 $READERS20 $(up 6) ;;
    U6) tests/Performance/run_with_sampling.sh $N $DUR -- --base $BASE $UPFLAGS --duration $DUR --think 1500 --vu nav:1:4 $(up 6) ;;
    U1x100k) tests/Performance/run_with_sampling.sh $N $DUR -- --wait-uploads --timeout 700 --base $BASE --duration $DUR --think 1500 --vu nav:1:4 --upload 1:C:/lspu_perf/files/c1_100k.xlsx ;;
  esac
  echo "--- rows added by this run:"; php tests/Performance/db_snapshot.php restore C:/lspu_perf/snap_$run.json | grep -E "removed" | tr '\n' ' '; echo
done
