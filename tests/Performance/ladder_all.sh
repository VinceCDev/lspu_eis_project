#!/bin/bash
# Grows the dataset step by step and runs the read-side suite at each size. Long-running (hours at 2M+); run in background.
cd "$(dirname "$0")/../.."
source tests/Performance/env.sh
for spec in "${@:-100000:L100k 500000:L500k 1000000:L1m 2000000:L2m}"; do
  size=${spec%%:*}; label=${spec##*:}
  echo "##### $(date +%T) seeding to $size"; php tests/Performance/seed_bulk.php --target=$size --chunk=100000 | tail -8
  echo "##### $(date +%T) ladder $label"; tests/Performance/ladder.sh $label 90
  echo "##### $(date +%T) done $label"
done
