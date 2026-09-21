// Load-test driver for the LSPU EIS admin app (Node 18+, no dependencies).
//
//   node tests/Performance/loadtest.mjs --name s4_mixed --duration 120 \
//        --vu dashboard:6 --vu reports:4 --vu alumni:2 --vu nav:4 \
//        --upload c1:C:/lspu_perf/files/c1_100k.xlsx:1 [--cold] [--base http://127.0.0.1:8091]
//
// --vu <profile>:<count>[:<campusIdx|super>]   profile in dashboard|reports|alumni|search|nav|export|location
// --upload <accountIdx>:<file>[:<campusIdx>]   one bulk import per flag (POSTs, then polls importStatus like the real UI)
// Every request is timed; results are written to tests/Performance/results/<name>.json
import fs from 'node:fs';

const args = process.argv.slice(2);
const opt = (k, d) => { const i = args.indexOf('--' + k); return i >= 0 ? args[i + 1] : d; };
const multi = (k) => args.flatMap((a, i) => (a === '--' + k ? [args[i + 1]] : []));
const BASE = opt('base', 'http://127.0.0.1:8091');
const NAME = opt('name', 'run');
const DURATION = Number(opt('duration', 60)) * 1000;
const TIMEOUT = Number(opt('timeout', 120)) * 1000;
const THINK = Number(opt('think', 1500));
const PERF = args.includes('--perfheaders');
let pendingUploads = 0;
const UNTIL = args.includes('--until-uploads'); // readers keep going until every import has finished (queue scenarios)
const ONCE = args.includes('--once'); // each virtual user runs its profile exactly once (cold/single-user measurements)

const ACCOUNTS = { super: 'super@perf.invalid' };
for (let i = 1; i <= 6; i++) ACCOUNTS[i] = `admin.campus${i}@perf.invalid`;
const PASSWORD = 'Perf#1234';

const cookies = {};
async function login(key) {
  if (cookies[key]) return cookies[key];
  const r = await fetch(BASE + '/login?action=login', {
    method: 'POST', redirect: 'manual',
    headers: { 'Content-Type': 'application/json', Origin: BASE, Accept: 'application/json' },
    body: JSON.stringify({ email: ACCOUNTS[key], password: PASSWORD }),
  });
  const txt = await r.text(); let j; try { j = JSON.parse(txt); } catch { throw new Error(`login ${key}: non-JSON (${r.status}): ${txt.slice(0, 300)}`); }
  if (!j.success) throw new Error(`login ${key} failed: ${JSON.stringify(j)}`);
  cookies[key] = r.headers.getSetCookie().map((c) => c.split(';')[0]).join('; ');
  return cookies[key];
}

const errBodies = [];
const samples = []; // {label, ms, status, bytes, t, err, perf}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const T0 = Date.now();

async function hit(label, key, path, { method = 'GET' } = {}) {
  const ck = await login(key);
  const url = BASE + path + (PERF ? (path.includes('?') ? '&' : '?') + 'perf=1' : '');
  const t = performance.now();
  const ac = new AbortController();
  const to = setTimeout(() => ac.abort(), TIMEOUT);
  const s = { label, t: Date.now() - T0, status: 0, bytes: 0, ms: 0, err: null };
  try {
    const r = await fetch(url, { method, headers: { Cookie: ck, Accept: 'application/json, text/html', Origin: BASE }, signal: ac.signal });
    const buf = await r.arrayBuffer();
    s.status = r.status; s.bytes = buf.byteLength;
    if (r.status >= 500 && errBodies.length < 8) errBodies.push({ label, path, status: r.status, body: Buffer.from(buf).toString('utf8').replace(/\s+/g, ' ').slice(0, 400) });
    if (PERF) s.perf = { total: +r.headers.get('x-perf-total-ms'), db: +r.headers.get('x-perf-db-ms'), q: +r.headers.get('x-perf-query-count'), dupe: +r.headers.get('x-perf-dupe-count'), mem: +r.headers.get('x-perf-peak-mem-mb') };
  } catch (e) {
    s.err = e.name === 'AbortError' ? 'timeout' : (e.cause?.code || e.message);
  } finally { clearTimeout(to); s.ms = performance.now() - t; samples.push(s); }
  return s;
}

const yrs = [2012, 2015, 2018, 2020, 2022, 2024];
const pick = (a) => a[Math.floor(Math.random() * a.length)];
const places = [['San Pablo City', 'Laguna'], ['Santa Cruz', 'Laguna'], ['Calamba', 'Laguna'], ['Lucena', 'Quezon'], ['Lipa', 'Batangas']];

// One "page visit" = the same request set the real page fires (see admin_*.js).
const PROFILES = {
  dashboard: async (k, p) => {
    await hit('dash:page', k, `/${p}_dashboard`);
    await Promise.all([
      hit('dash:stats', k, `/${p}_dashboard?action=stats`),
      hit('dash:employmentStatusByCampus', k, `/${p}_dashboard?action=employmentStatusByCampus`),
      hit('dash:courseWorkAlignment', k, `/${p}_dashboard?action=courseWorkAlignment`),
    ]);
  },
  location: async (k, p) => { // "Alumni Location": marker popup, paged
    const [c, pr] = pick(places);
    await hit('loc:alumniAtLocation', k, `/${p}_dashboard?action=alumniAtLocation&city=${encodeURIComponent(c)}&province=${encodeURIComponent(pr)}&page=${1 + Math.floor(Math.random() * 3)}`);
  },
  reports: async (k, p) => {
    await hit('rep:page', k, `/${p}_reports`);
    await Promise.all([
      hit('rep:colleges', k, `/${p}_reports?action=colleges`),
      hit('rep:years', k, `/${p}_reports?action=years`),
      hit('rep:summary', k, `/${p}_reports?action=summary`),
    ]);
    await sleep(THINK);
    await hit('rep:summary(filter year)', k, `/${p}_reports?action=summary&year_graduated=${pick(yrs)}`); // date-range/year filter
  },
  export: async (k, p) => { await hit('rep:fullData(export)', k, `/${p}_reports?action=fullData`); },
  alumni: async (k, p) => { // the Alumni page: shell + the FIRST PAGE of the server-side paged list (what the UI now requests)
    await hit('alumni:page', k, `/${p}_alumni`);
    await hit('alumni:paginatedList(p1)', k, `/${p}_alumni?action=paginatedList&per_page=5&page=1`);
  },
  alumniLegacy: async (k, p) => { // the OLD behaviour: load every alumnus (now refused above 25,000 rows with HTTP 413)
    await hit('alumni:list(all)', k, `/${p}_alumni?action=list`);
  },
  search: async (k, p) => { // server-side (prefix) search + pagination: what the Alumni page's search box / pager call
    const term = pick(['Santos', 'Reyes', 'Cruz', 'Garcia', 'Dela Cruz', 'Zq']);
    await hit('search:paginatedList(q)', k, `/${p}_alumni?action=paginatedList&per_page=25&page=${1 + Math.floor(Math.random() * 20)}&search=${encodeURIComponent(term)}`);
    await hit('search:paginatedList(page)', k, `/${p}_alumni?action=paginatedList&per_page=25&page=${1 + Math.floor(Math.random() * 200)}`);
  },
  nav: async (k, p) => {
    await hit('nav:profile', k, `/${p}_profile?action=details`);
    await hit('nav:settings', k, `/${p}_settings`);
    await hit('nav:notification', k, `/${p}_notification`);
    await hit('nav:user', k, `/${p}_user`);
  },
};

async function vu(profile, key, stopAt) {
  const prefix = key === 'super' ? 'superadmin' : 'admin';
  const fn = PROFILES[profile];
  if (!fn) throw new Error('unknown profile ' + profile);
  await sleep(Math.random() * 1000);
  do { await fn(key, prefix); if (ONCE) break; await sleep(THINK * (0.5 + Math.random())); } while (Date.now() < stopAt || (UNTIL && pendingUploads > 0));
}

const uploads = [];
async function doUpload(acct, file, year = 2023, campusId = '') {
  const ck = await login(acct);
  const rec = { acct, file: file.split(/[\\/]/).pop(), bytes: fs.statSync(file).size, t0: Date.now() - T0, progress: [], final: null, http: 0, err: null };
  uploads.push(rec);
  const fd = new FormData();
  fd.append('file', new Blob([fs.readFileSync(file)]), rec.file);
  fd.append('year', String(year));
  if (campusId) fd.append('campus_id', String(campusId));
  const ts = performance.now();
  const page = `/${acct === 'super' ? 'superadmin' : 'admin'}_alumni`;
  try {
    // Queue architecture: the POST only stores the file + queues the import and returns at once...
    const r = await fetch(BASE + `${page}?action=importEmploymentReport`, { method: 'POST', headers: { Cookie: ck, Origin: BASE, Accept: 'application/json' }, body: fd });
    rec.http = r.status; rec.post_ms = Math.round(performance.now() - ts);
    const txt = await r.text(); let j; try { j = JSON.parse(txt); } catch { rec.err = txt.slice(0, 200); }
    if (j && j.success && j.import) {
      rec.import_id = j.import_id;
      // ...then progress is polled from import_jobs (what the UI does), every 2 s, until it is finished.
      let imp = j.import;
      const deadline = performance.now() + TIMEOUT;
      while (['queued', 'processing'].includes(imp.status) && performance.now() < deadline) {
        rec.progress.push([Math.round(performance.now() - ts), imp.status, imp.processed_rows, imp.total_rows]);
        if (imp.status === 'processing' && rec.started_ms === undefined) rec.started_ms = Math.round(performance.now() - ts);
        await sleep(2000);
        try { const pr = await fetch(BASE + `${page}?action=importStatus&id=${rec.import_id}`, { headers: { Cookie: ck, Accept: 'application/json' } }); const pj = await pr.json(); if (pj.success) imp = pj.import; } catch { /* transient */ }
      }
      rec.final = { ...imp, success: imp.status === 'completed', phase: imp.status };
      rec.progress.push([Math.round(performance.now() - ts), imp.status, imp.processed_rows, imp.total_rows]);
    } else if (j) { rec.final = { success: false, message: j.message }; }
  } catch (e) { rec.err = e.cause?.code || e.message; }
  rec.ms = Math.round(performance.now() - ts);
  const last = rec.progress[rec.progress.length - 1];
  rec.rows_done_when_ended = last ? last[2] : 0;
  if (rec.started_ms !== undefined) { rec.queue_wait_ms = rec.started_ms; rec.processing_ms = rec.ms - rec.started_ms; }
  pendingUploads--;
  return rec;
}

function pct(a, p) { if (!a.length) return null; const s = [...a].sort((x, y) => x - y); return s[Math.min(s.length - 1, Math.ceil((p / 100) * s.length) - 1)]; }
function summarize(rows, spanMs) {
  const ms = rows.map((r) => r.ms);
  const ok = rows.filter((r) => r.status >= 200 && r.status < 400);
  return {
    n: rows.length, rps: +(rows.length / (spanMs / 1000)).toFixed(2),
    avg: Math.round(ms.reduce((a, b) => a + b, 0) / (ms.length || 1)), p50: Math.round(pct(ms, 50) ?? 0), p95: Math.round(pct(ms, 95) ?? 0), p99: Math.round(pct(ms, 99) ?? 0), max: Math.round(Math.max(0, ...ms)),
    err_5xx: rows.filter((r) => r.status >= 500).length,
    s502: rows.filter((r) => r.status === 502).length, s503: rows.filter((r) => r.status === 503).length, s504: rows.filter((r) => r.status === 504).length,
    timeouts: rows.filter((r) => r.err === 'timeout').length, conn_err: rows.filter((r) => r.err && r.err !== 'timeout').length, ok: ok.length,
    kb: Math.round(rows.reduce((a, r) => a + r.bytes, 0) / (rows.length || 1) / 1024),
  };
}

async function main() {
  if (args.includes('--warm-login')) { for (const k of Object.keys(ACCOUNTS)) await login(k); }
  const vus = multi('vu').map((v) => { const [p, n, who] = v.split(':'); return { p, n: +n, who: who || 'super' }; });
  const ups = multi('upload');
  pendingUploads = ups.length;
  const stopAt = Date.now() + DURATION;
  const tasks = [];
  for (const { p, n, who } of vus) for (let i = 0; i < n; i++) tasks.push(vu(p, who === 'super' ? 'super' : Number(who), stopAt));
  // Uploads start at t=0, in parallel with the readers (they are NOT bounded by --duration: we wait for them or their server-side kill).
  const upTasks = ups.map((u) => { const [acct, file] = [u.split(':')[0], u.split(':').slice(1).join(':')]; return doUpload(acct === 'super' ? 'super' : Number(acct), file, 2023, acct === 'super' ? 7 : ''); });
  const waitUploads = args.includes('--wait-uploads');
  await Promise.all(tasks);
  const readersEnd = Date.now() - T0;
  if (waitUploads) await Promise.all(upTasks); else if (upTasks.length) console.error(`(readers done at ${Math.round(readersEnd / 1000)}s; uploads still running -> not waiting; use --wait-uploads to wait)`);

  // Window in which at least one import was still running (uploads that never finish inside the run count until the end).
  const upEnd = uploads.length ? Math.max(...uploads.map((u) => (u.ms !== undefined ? u.t0 + u.ms : readersEnd))) : null;
  const byLabel = {};
  for (const s of samples) (byLabel[s.label] ||= []).push(s);
  const span = Math.max(readersEnd, 1);
  const out = { name: NAME, base: BASE, duration_s: Math.round(span / 1000), overall: summarize(samples, span), byLabel: {}, uploads, timeline: null, errBodies, upload_window_ms: upEnd };
  for (const [l, r] of Object.entries(byLabel)) {
    out.byLabel[l] = summarize(r, span);
    if (PERF) { const pf = r.filter((x) => x.perf).map((x) => x.perf); if (pf.length) out.byLabel[l].perf = { db_ms_avg: Math.round(pf.reduce((a, b) => a + b.db, 0) / pf.length), q_avg: Math.round(pf.reduce((a, b) => a + b.q, 0) / pf.length), dupe_avg: Math.round(pf.reduce((a, b) => a + b.dupe, 0) / pf.length), mem_mb_max: Math.max(...pf.map((x) => x.mem)) }; }
  }
  if (upEnd) {
    const during = samples.filter((x) => x.t <= upEnd); const after = samples.filter((x) => x.t > upEnd);
    out.during_upload = summarize(during, Math.max(upEnd, 1)); out.after_upload = after.length ? summarize(after, Math.max(readersEnd - upEnd, 1)) : null;
    console.log(`during-upload window 0-${Math.round(upEnd / 1000)}s: n=${during.length} p50=${out.during_upload.p50}ms p95=${out.during_upload.p95}ms p99=${out.during_upload.p99}ms 5xx=${out.during_upload.err_5xx} t/o=${out.during_upload.timeouts}`);
  }
  // 10s buckets of reader latency, to see degradation while an upload runs
  const buckets = {}; for (const s of samples) { const b = Math.floor(s.t / 10000) * 10; (buckets[b] ||= []).push(s.ms); }
  out.timeline = Object.entries(buckets).map(([b, v]) => ({ t: +b, n: v.length, p50: Math.round(pct(v, 50)), p95: Math.round(pct(v, 95)) }));
  fs.mkdirSync(new URL('./results/', import.meta.url), { recursive: true });
  fs.writeFileSync(new URL(`./results/${NAME}.json`, import.meta.url), JSON.stringify(out, null, 1));

  console.log(`\n=== ${NAME}  (${out.duration_s}s, ${samples.length} requests, ${out.overall.rps} req/s) ===`);
  console.log('label'.padEnd(34), 'n'.padStart(5), 'avg'.padStart(7), 'p50'.padStart(7), 'p95'.padStart(7), 'p99'.padStart(7), 'max'.padStart(7), '5xx'.padStart(4), 't/o'.padStart(4), 'KB'.padStart(6), PERF ? ' db_ms  queries dupes' : '');
  for (const [l, v] of Object.entries(out.byLabel)) console.log(l.padEnd(34), String(v.n).padStart(5), String(v.avg).padStart(7), String(v.p50).padStart(7), String(v.p95).padStart(7), String(v.p99).padStart(7), String(v.max).padStart(7), String(v.err_5xx).padStart(4), String(v.timeouts + v.conn_err).padStart(4), String(v.kb).padStart(6), v.perf ? `  ${v.perf.db_ms_avg}  ${v.perf.q_avg}  ${v.perf.dupe_avg}` : '');
  const o = out.overall; console.log('ALL'.padEnd(34), String(o.n).padStart(5), String(o.avg).padStart(7), String(o.p50).padStart(7), String(o.p95).padStart(7), String(o.p99).padStart(7), String(o.max).padStart(7), String(o.err_5xx).padStart(4), String(o.timeouts + o.conn_err).padStart(4));
  for (const u of uploads) console.log(`upload acct=${u.acct} ${u.file}: http=${u.http} post=${u.post_ms ?? '-'}ms queue_wait=${u.queue_wait_ms ?? '-'}ms processing=${u.processing_ms ?? '-'}ms total=${u.ms ?? '(running)'}ms rows_done=${u.rows_done_when_ended} final=${JSON.stringify(u.final)?.slice(0, 200)} err=${u.err}`);
  if (errBodies.length) console.log('5xx samples:', JSON.stringify(errBodies, null, 1).slice(0, 1800));
  process.exit(0);
}
main().catch((e) => { console.error(e); process.exit(1); });
