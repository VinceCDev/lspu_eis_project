# OS-level sampler: CPU (cores busy) + working set of the scratch mysqld and the private Apache, plus system CPU% / free RAM.
# usage: powershell -File sampler.ps1 -Out C:\lspu_perf\results\x_os.csv -Seconds 120 [-Interval 2]
param([string]$Out, [int]$Seconds = 60, [int]$Interval = 2, [string]$HttpdPidFile = 'C:/lspu_perf/apache/httpd.pid')
$my = (Get-CimInstance Win32_Process -Filter "Name='mysqld.exe'" | Where-Object { $_.CommandLine -like '*lspu_perf*' }).ProcessId
$httpdParent = [int](Get-Content $HttpdPidFile)
function WorkerIds { @((Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -like '*artisan*queue:work*' -or $_.CommandLine -like '*artisan*schedule:work*' }).ProcessId) }
function HttpdIds { @($httpdParent) + @((Get-CimInstance Win32_Process -Filter "ParentProcessId=$httpdParent").ProcessId) }
function Cpu($ids) { $s = 0.0; foreach ($i in $ids) { $p = Get-Process -Id $i -ErrorAction SilentlyContinue; if ($p) { $s += $p.TotalProcessorTime.TotalSeconds } }; $s }
function Ws($ids)  { $s = 0.0; foreach ($i in $ids) { $p = Get-Process -Id $i -ErrorAction SilentlyContinue; if ($p) { $s += $p.WorkingSet64 } }; [math]::Round($s / 1MB) }
"t,sys_cpu_pct,mysql_cores,httpd_cores,mysql_ws_mb,httpd_ws_mb,free_ram_mb,mysql_threads,httpd_threads,worker_cores,worker_ws_mb,workers" | Out-File $Out -Encoding ascii
$h = HttpdIds; $w = WorkerIds; $c0m = Cpu @($my); $c0h = Cpu $h; $c0w = Cpu $w; $t0 = Get-Date; $prev = $t0
$end = $t0.AddSeconds($Seconds)
while ((Get-Date) -lt $end -and -not (Test-Path "$Out.stop")) {
  Start-Sleep -Seconds $Interval
  $now = Get-Date; $dt = ($now - $prev).TotalSeconds; $prev = $now
  $h = HttpdIds
  $w = WorkerIds; $c1w = Cpu $w
  $c1m = Cpu @($my); $c1h = Cpu $h
  $sys = (Get-Counter '\Processor(_Total)\% Processor Time' -ErrorAction SilentlyContinue).CounterSamples[0].CookedValue
  $free = (Get-Counter '\Memory\Available MBytes' -ErrorAction SilentlyContinue).CounterSamples[0].CookedValue
  $thm = (Get-Process -Id $my).Threads.Count; $thh = 0; foreach ($i in $h) { $p = Get-Process -Id $i -ErrorAction SilentlyContinue; if ($p) { $thh += $p.Threads.Count } }
  ("{0:F0},{1:F0},{2:F2},{3:F2},{4},{5},{6:F0},{7},{8},{9:F2},{10},{11}" -f ($now - $t0).TotalSeconds, $sys, (($c1m - $c0m) / $dt), (($c1h - $c0h) / $dt), (Ws @($my)), (Ws $h), $free, $thm, $thh, ([math]::Max(0, ($c1w - $c0w) / $dt)), (Ws $w), @($w).Count) | Out-File $Out -Append -Encoding ascii
  $c0m = $c1m; $c0h = $c1h; $c0w = $c1w
}
