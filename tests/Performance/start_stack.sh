#!/bin/bash
# Starts (1) scratch MySQL on :3391 and (2) private Apache on :8091, both isolated from the dev stack.
cd "$(dirname "$0")/../.."
source tests/Performance/env.sh
powershell -NoProfile -Command "Start-Process -FilePath 'C:\Program Files\MySQL\MySQL Server 9.6\bin\mysqld.exe' -ArgumentList '--defaults-file=C:/lspu_perf/my.ini','--console' -RedirectStandardError C:\lspu_perf\mysqld.log -WindowStyle Hidden"
sleep 8
powershell -NoProfile -Command "Start-Process -FilePath 'C:\xampp\apache\bin\httpd.exe' -ArgumentList '-f','C:/xampp/htdocs/lspu_eis_laravel/tests/Performance/httpd-perf.conf' -WindowStyle Hidden"
