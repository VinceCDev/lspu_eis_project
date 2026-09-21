#!/bin/bash
# Read-side measurement suite for the CURRENT dataset size. Run after seed_bulk.php has topped the DB up.
#   tests/Performance/ladder.sh <label> [mixed_seconds]
# Produces tests/Performance/results/<label>_*.json ; then `python tests/Performance/ladder_table.py <label>...`
cd "$(dirname "$0")/../.."
source tests/Performance/env.sh
L=$1; MIXED=${2:-90}
N="node tests/Performance/loadtest.mjs --once --perfheaders --timeout 180 --base ${BASE:-http://127.0.0.1:8091}"
clear() { php tests/Performance/clear_cache.php; }

for who in super 1; do
  clear; $N --name ${L}_dashboard_${who}_cold --vu dashboard:1:$who   | grep -E "^(dash:|ALL)"
  $N --name ${L}_dashboard_${who}_warm --vu dashboard:1:$who          | grep -E "^ALL"
  clear; $N --name ${L}_reports_${who}_cold  --vu reports:1:$who      | grep -E "^(rep:|ALL)"
  $N --name ${L}_reports_${who}_warm  --vu reports:1:$who             | grep -E "^ALL"
done
$N --name ${L}_location --vu location:5:super                           | grep -E "^(loc:|ALL)"
$N --name ${L}_search   --vu search:5:1                                 | grep -E "^(search:|ALL)"
$N --name ${L}_nav      --vu nav:1:1                                    | grep -E "^(nav:|ALL)"
# the two unbounded endpoints, campus-1 admin (~30% of all alumni) - what the UI really calls
$N --name ${L}_alumni_list_c1 --vu alumni:1:1                           | grep -E "^(alumni:|ALL)|5xx" -A3
$N --name ${L}_export_c1      --vu export:1:1                           | grep -E "^(rep:|ALL)"
# concurrent mixed read workload, cold start (cache empty) then continues through TTL expiries
clear
tests/Performance/run_with_sampling.sh ${L}_mixed $MIXED -- --duration $MIXED --think 1500 --perfheaders \
  --vu dashboard:3:super --vu dashboard:1:1 --vu reports:2:super --vu reports:1:2 --vu search:2:3 --vu location:1:super --vu nav:2:4 | tail -40
