"""Builds the dataset-scalability tables from tests/Performance/results/<label>_*.json.
   python tests/Performance/ladder_table.py L10k L100k L500k L1m L2m
"""
import json, os, sys

R = os.path.join(os.path.dirname(__file__), 'results')


def load(name):
    p = os.path.join(R, name + '.json')
    return json.load(open(p, encoding='utf-8')) if os.path.exists(p) else None


def lab(d, label, key='p95'):
    if not d:
        return None
    v = d['byLabel'].get(label)
    return v[key] if v else None


def fmt(v):
    if v is None:
        return '-'
    return f'{v/1000:.1f}s' if v >= 1000 else f'{v}ms'


def col(labels, title, fn):
    print(f'\n### {title}')
    print('| endpoint | ' + ' | '.join(labels) + ' |')
    print('|---|' + '---:|' * len(labels))
    return fn


labels = sys.argv[1:]
rows = [
    ('dash:stats (cold, superadmin)', lambda L: lab(load(f'{L}_dashboard_super_cold'), 'dash:stats')),
    ('dash:employmentStatusByCampus (cold)', lambda L: lab(load(f'{L}_dashboard_super_cold'), 'dash:employmentStatusByCampus')),
    ('dash:courseWorkAlignment (cold)', lambda L: lab(load(f'{L}_dashboard_super_cold'), 'dash:courseWorkAlignment')),
    ('dash:stats (cold, campus admin)', lambda L: lab(load(f'{L}_dashboard_1_cold'), 'dash:stats')),
    ('dashboard cached (warm) all 4 calls, max', lambda L: (load(f'{L}_dashboard_super_warm') or {}).get('overall', {}).get('max')),
    ('rep:summary (cold, superadmin)', lambda L: lab(load(f'{L}_reports_super_cold'), 'rep:summary')),
    ('rep:summary(filter year) (superadmin)', lambda L: lab(load(f'{L}_reports_super_cold'), 'rep:summary(filter year)')),
    ('rep:colleges + years (cold)', lambda L: (lab(load(f'{L}_reports_super_cold'), 'rep:colleges') or 0) + (lab(load(f'{L}_reports_super_cold'), 'rep:years') or 0) or None),
    ('rep:summary warm', lambda L: lab(load(f'{L}_reports_super_warm'), 'rep:summary')),
    ('alumni map popup (alumniAtLocation) p95', lambda L: lab(load(f'{L}_location'), 'loc:alumniAtLocation')),
    ('search paginatedList(q) p95', lambda L: lab(load(f'{L}_search'), 'search:paginatedList(q)')),
    ('pagination paginatedList(page) p95', lambda L: lab(load(f'{L}_search'), 'search:paginatedList(page)')),
    ('alumni:list(all) campus-1 (UI path)', lambda L: lab(load(f'{L}_alumni_list_c1'), 'alumni:list(all)', 'max')),
    ('  ... response size', lambda L: None),
    ('rep:fullData export campus-1', lambda L: lab(load(f'{L}_export_c1'), 'rep:fullData(export)', 'max')),
]
print('| endpoint | ' + ' | '.join(labels) + ' |')
print('|---|' + '---:|' * len(labels))
for name, fn in rows:
    if name.startswith('  ... response'):
        vals = []
        for L in labels:
            d = load(f'{L}_alumni_list_c1')
            v = d['byLabel'].get('alumni:list(all)') if d else None
            vals.append(f"{v['kb']/1024:.0f} MB" + (f" (5xx={v['err_5xx']})" if v and v['err_5xx'] else '') if v else '-')
        print(f'| {name} | ' + ' | '.join(vals) + ' |')
        continue
    vals = []
    for L in labels:
        try:
            vals.append(fmt(fn(L)))
        except Exception:
            vals.append('-')
    print(f'| {name} | ' + ' | '.join(vals) + ' |')

print('\n### errors on unbounded endpoints (5xx / timeout)')
for L in labels:
    for k in ('alumni_list_c1', 'export_c1'):
        d = load(f'{L}_{k}')
        if d:
            o = d['overall']
            print(f"{L:6} {k:16} 5xx={o['err_5xx']} timeouts={o['timeouts']} max={fmt(o['max'])} bytes~{o.get('kb', 0)}KB")

print('\n### mixed concurrent workload (10 VUs, cold cache start)')
print('| dataset | reqs | req/s | avg | p50 | p95 | p99 | max | 5xx | timeouts |')
print('|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|')
for L in labels:
    d = load(f'{L}_mixed')
    if d:
        o = d['overall']
        print(f"| {L} | {o['n']} | {o['rps']} | {fmt(o['avg'])} | {fmt(o['p50'])} | {fmt(o['p95'])} | {fmt(o['p99'])} | {fmt(o['max'])} | {o['err_5xx']} | {o['timeouts'] + o['conn_err']} |")
