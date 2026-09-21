# Keeps this Windows machine (incl. Modern Standby laptops) running while load tests are in progress.
# Process-scoped: uses PowerCreateRequest/PowerSetRequest (Execution + System + Display required); no power-plan setting is changed
# and everything is released when this process exits. Kill the process when the tests are done.
Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public static class PowerReq {
  [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)] public struct REASON_CONTEXT { public uint Version; public uint Flags; public string SimpleReasonString; }
  [DllImport("kernel32.dll", CharSet = CharSet.Unicode)] public static extern IntPtr PowerCreateRequest(ref REASON_CONTEXT ctx);
  [DllImport("kernel32.dll")] public static extern bool PowerSetRequest(IntPtr h, int type);
  [DllImport("kernel32.dll")] public static extern uint SetThreadExecutionState(uint f);
  public static void Hold(string why) {
    var c = new REASON_CONTEXT { Version = 0, Flags = 1, SimpleReasonString = why };   // POWER_REQUEST_CONTEXT_SIMPLE_STRING
    IntPtr h = PowerCreateRequest(ref c);
    PowerSetRequest(h, 0);   // PowerRequestDisplayRequired
    PowerSetRequest(h, 1);   // PowerRequestSystemRequired
    PowerSetRequest(h, 2);   // PowerRequestAwayModeRequired
    PowerSetRequest(h, 3);   // PowerRequestExecutionRequired
    SetThreadExecutionState(0x80000001 | 0x00000002);
  }
}
'@
[PowerReq]::Hold('LSPU EIS load test in progress')
while ($true) { Start-Sleep -Seconds 20; [void][PowerReq]::SetThreadExecutionState(0x80000001 -bor 0x00000002) }
