# Emulates the 4-vCPU production VPS: pins the scratch mysqld + private Apache(s) to 4 logical CPUs (mask 0xF0 = CPUs 4-7, the
# E-cores on this i5-1334U) and leaves CPUs 0-3 and 8-11 for the load generator / samplers.
# usage: powershell -File pin_stack.ps1 [-Mask 240]
param([int]$Mask = 240)
$ids = @((Get-CimInstance Win32_Process -Filter "Name='mysqld.exe'" | Where-Object { $_.CommandLine -like '*lspu_perf*' }).ProcessId)
foreach ($pf in 'C:\lspu_perf\apache\httpd.pid', 'C:\lspu_perf\apache\httpd_after.pid') {
  if (Test-Path $pf) { $par = [int](Get-Content $pf); if (Get-Process -Id $par -ErrorAction SilentlyContinue) { $ids += $par; $ids += @((Get-CimInstance Win32_Process -Filter "ParentProcessId=$par").ProcessId) } }
}
$ids += @((Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -like '*artisan*queue:work*' -or $_.CommandLine -like '*artisan*schedule:work*' }).ProcessId)
foreach ($i in $ids) { try { (Get-Process -Id $i).ProcessorAffinity = [IntPtr]$Mask; "pinned $i -> 0x{0:X}" -f $Mask } catch { "skip $i : $($_.Exception.Message)" } }
