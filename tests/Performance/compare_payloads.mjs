// Proves the optimised build returns the SAME numbers as the current build for the dashboard / report endpoints.
//   node tests/Performance/compare_payloads.mjs   (both Apaches up: 8091 = current, 8092 = optimised; run from an env.sh shell)
// The two apps share the cache table, so the cache is emptied before each build is asked.
import { execSync } from 'node:child_process';

const BUILDS = { before: 'http://127.0.0.1:8091', after: 'http://127.0.0.1:8092' };
const ACCTS = { super: ['super@perf.invalid', 'superadmin'], c1: ['admin.campus1@perf.invalid', 'admin'] };
const PATHS = (p) => [
  `/${p}_dashboard?action=stats`, `/${p}_dashboard?action=employmentStatusByCampus`, `/${p}_dashboard?action=courseWorkAlignment`,
  `/${p}_dashboard?action=alumniAtLocation&city=San%20Pablo%20City&province=Laguna&page=1`,
  `/${p}_reports?action=summary`, `/${p}_reports?action=summary&year_graduated=2020`, `/${p}_reports?action=colleges`, `/${p}_reports?action=years`,
];
async function login(base, email) {
  const r = await fetch(base + '/login?action=login', { method: 'POST', headers: { 'Content-Type': 'application/json', Origin: base, Accept: 'application/json' }, body: JSON.stringify({ email, password: 'Perf#1234' }) });
  const j = await r.json(); if (!j.success) throw new Error('login ' + email + ' ' + JSON.stringify(j));
  return r.headers.getSetCookie().map((c) => c.split(';')[0]).join('; ');
}
const canon = (v) => JSON.stringify(v, (k, x) => (x && typeof x === 'object' && !Array.isArray(x) ? Object.fromEntries(Object.entries(x).sort(([a], [b]) => a.localeCompare(b))) : x));
const out = {};
for (const [build, base] of Object.entries(BUILDS)) {
  for (const [who, [email, prefix]] of Object.entries(ACCTS)) {
    execSync('php tests/Performance/clear_cache.php');
    const ck = await login(base, email);
    for (const path of PATHS(prefix)) {
      const t = Date.now();
      const r = await fetch(base + path, { headers: { Cookie: ck, Accept: 'application/json' }, signal: AbortSignal.timeout(600000) });
      let body = null; try { body = await r.json(); } catch { body = { _nonjson: r.status }; }
      (out[`${who} ${path}`] ||= {})[build] = { status: r.status, ms: Date.now() - t, body };
    }
  }
}
let bad = 0;
for (const [k, v] of Object.entries(out)) {
  const same = canon(v.before.body) === canon(v.after.body) && v.before.status === v.after.status;
  if (!same) bad++;
  console.log(`${same ? 'SAME ' : 'DIFF '} ${k.padEnd(88)} before ${String(v.before.ms).padStart(6)}ms  after ${String(v.after.ms).padStart(6)}ms  (cold, first request)`);
  if (!same) console.log('   before:', canon(v.before.body).slice(0, 300), '\n   after :', canon(v.after.body).slice(0, 300));
}
console.log(bad ? `\n${bad} endpoint(s) differ` : '\nALL endpoints return identical payloads');
