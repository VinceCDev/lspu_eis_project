"""Builds the concurrency-scenario tables from tests/Performance/results/*.json and C:/lspu_perf/results/*_os.csv|_db.csv.
   python tests/Performance/scenario_table.py before_50k_R0 before_50k_R1 ... after_100k_R6w
"""
import csv, json, os, sys

R = os.path.join(os.path.dirname(__file__), 'results')
S = 'C:/lspu_perf/results'


import re
LOGS = ['scenarios_before.log', 'scen_before2.log', 'scen_after.log', 'scen_queue2.log', 'scen_queue3.log']


def imported_rows():
    out = {}
    for lg in LOGS:
        path = 'C:/lspu_perf/' + lg
        if not os.path.exists(path):
            continue
        cur = None
        for line in open(path, encoding='utf-8', errors='ignore'):
            m = re.match(r'#+ (\S+)\s+\(', line)
            if m:
                cur = m.group(1)
            m = re.search(r'alumni: removed (\d+) rows', line)
            if m and cur:
                out[cur] = int(m.group(1))
    return out


ROWS = imported_rows()
NOTES = {'after_100k_R0': 'HTTP 500s = Blade view-compile race on the first hits of a fresh Apache (harness artefact)'}


def load(n):
    p = os.path.join(R, n + '.json')
    return json.load(open(p, encoding='utf-8')) if os.path.exists(p) else None


def fmt(v):
    if v is None:
        return '-'
    return f'{v/1000:.1f}s' if v >= 1000 else f'{int(v)}ms'


def res(n):
    out = {}
    try:
        os_rows = list(csv.DictReader(open(f'{S}/{n}_os.csv')))
        db_rows = list(csv.DictReader(open(f'{S}/{n}_db.csv')))
        f = lambda rows, k: [float(r[k]) for r in rows if r.get(k) not in (None, '')]
        out['mysql_cores'] = sum(f(os_rows, 'mysql_cores')) / max(1, len(f(os_rows, 'mysql_cores')))
        out['php_rss_max'] = max(f(os_rows, 'httpd_ws_mb') or [0])
        out['free_min'] = min(f(os_rows, 'free_ram_mb') or [0])
        dl = f(db_rows, 'deadlocks') or [0]
        out['deadlocks'] = int(max(dl) - dl[0])   # InnoDB lock_deadlocks is a cumulative counter: report the increase during the run
        out['lockwait_s'] = sum(f(db_rows, 'd_Innodb_row_lock_time')) / 1000
        out['ins'] = int(sum(f(db_rows, 'd_Innodb_rows_inserted')))
        out['gap'] = any(int(float(b['t'])) - int(float(a['t'])) > 6 for a, b in zip(os_rows, os_rows[1:]))
    except Exception:
        pass
    return out


print('| run | uploads | alumni rows imported | requests (req/s) | dash:stats p95 | rep:summary p95 | all p50 | all p95 | all p99 | 5xx | timeouts | mysqld cores (avg) | PHP RSS max | deadlocks | notes |')
print('|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---|')
for n in sys.argv[1:]:
    d = load(n)
    if not d:
        print(f'| {n} | (missing) |')
        continue
    o = d['overall']
    bl = d['byLabel']
    win = d.get('during_upload')
    ups = d.get('uploads', [])
    ok = sum(1 for u in ups if u.get('final') and u['final'].get('success'))
    up_txt = f'{ok}/{len(ups)} finished' if ups else '-'
    if ups:
        done = [u['ms'] for u in ups if u.get('final') and u['final'].get('success')]
        if done:
            up_txt += f' ({min(done)/1000:.0f}-{max(done)/1000:.0f}s)'
    r = res(n)
    src = win or o
    notes = []
    if r.get('gap'):
        notes.append('**INVALID: machine slept**')
    if ups and any(u.get('ms') is None for u in ups) and not any(u.get('final') for u in ups):
        up_txt = f'{len(ups)} started, none finished when the harness left'
    if n in NOTES:
        notes.append(NOTES[n])
    rows_imp = ROWS.get(n)
    print(f"| {n} | {up_txt} | {('{:,}'.format(rows_imp) if rows_imp is not None else '-')} | {o['n']} ({o['rps']}) | {fmt(bl.get('dash:stats', {}).get('p95'))} | {fmt(bl.get('rep:summary', {}).get('p95'))} | {fmt(src['p50'])} | {fmt(src['p95'])} | {fmt(src['p99'])} | {src['err_5xx']} | {src['timeouts'] + src['conn_err']} | "
          f"{r.get('mysql_cores', 0):.1f} | {r.get('php_rss_max', 0):.0f} MB | {r.get('deadlocks', '-')} | {'; '.join(notes)} |")
