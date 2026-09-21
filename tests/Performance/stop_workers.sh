#!/bin/bash
# Stops every queue:work / schedule:work process of this stack.
powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" | Where-Object { \$_.CommandLine -like '*artisan*queue:work*' -or \$_.CommandLine -like '*artisan*schedule:work*' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force; 'stopped ' + \$_.ProcessId }"
