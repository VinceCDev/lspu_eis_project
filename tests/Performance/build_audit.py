"""Assembles tests/Performance/AUDIT.md from the template + the measured tables, and (optionally) a standalone HTML page.
   python tests/Performance/build_audit.py <template.md> <out.md> [<out.html> <html_template.html>]
Numbers in the tables are generated from tests/Performance/results/*.json - nothing is typed by hand.
"""
import json, subprocess, sys, os

here = os.path.dirname(os.path.abspath(__file__))
tpl, out_md = sys.argv[1], sys.argv[2]
env = dict(os.environ, PYTHONIOENCODING='utf-8')


def run(script, *args):
    return subprocess.run([sys.executable, os.path.join(here, script), *args], capture_output=True, text=True, encoding='utf-8', env=env).stdout


ladder = run('ladder_table.py', 'L10k', 'L100k', 'L500k', 'L1m', 'L2m')
RUNS = [
    'before_50k_R0', 'before_50k_R1', 'before_50k_R6', 'before_50k_W6', 'before_50k_U1x100k', 'before_50k_R1w', 'before_50k_R6w',
    'after_100k_R0', 'after_100k_R0w', 'after_100k_R1', 'after_100k_R1w', 'after_100k_R3', 'after_100k_R6', 'after_100k_R6w',
    'after_100k_W6', 'after_100k_W6w', 'after_100k_U1x100k',
]
scen = run('scenario_table.py', *[r for r in RUNS if os.path.exists(os.path.join(here, 'results', r + '.json'))])

md = open(tpl, encoding='utf-8').read().replace('{{LADDER_TABLE}}', ladder.strip()).replace('{{SCENARIO_TABLE}}', scen.strip())
open(out_md, 'w', encoding='utf-8').write(md)
print('wrote', out_md, len(md), 'chars')

if len(sys.argv) >= 5:
    html = open(sys.argv[4], encoding='utf-8').read()
    payload = json.dumps(md).replace('</', '<\\/')
    open(sys.argv[3], 'w', encoding='utf-8').write(html.replace('__MARKDOWN_JSON__', payload).replace('__MARKDOWN_TEXT__', md.replace('&', '&amp;').replace('<', '&lt;')))
    print('wrote', sys.argv[3])
