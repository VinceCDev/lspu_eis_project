"""
Generate a synthetic LSPU "Data on Employment" workbook (same layout as
sample-employment-report-*.xlsx) with N graduate rows, for import load tests.

  python tests/Performance/make_workbook.py --rows 100000 --tag c1 --out C:/lspu_perf/files/c1_100k.xlsx

--tag keeps names/emails unique per file so several campuses can import
"simultaneously" without colliding on the unique `user.email` constraint.
40% of rows have a blank e-mail (=> placeholder e-mail path), like real tracer sheets.
"""
import argparse, random, re, sys
from datetime import date
import openpyxl

PROGRAMS = ['BSIT', 'BSCS', 'BSIS', 'BSCE', 'BSEE', 'BSTM', 'BSHM', 'BSCRIM', 'BSA', 'BSBA', 'BSOA', 'BSPSY', 'BEED', 'BSED', 'BSBIO', 'BSAGRI']
FIRST = ['Maria', 'Juan', 'Jose', 'Ana', 'Mark', 'John', 'Liza', 'Carlo', 'Nicole', 'Ryan', 'Angel', 'Kevin', 'Joy', 'Paul', 'Grace', 'James', 'Jane', 'Mike', 'Rose', 'Ken']
LAST = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Andres', 'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Aquino', 'Navarro', 'Salazar', 'Dela Cruz', 'Gonzales', 'Perez']
STATUS = ['Regular', 'Contractual', 'Probationary', 'Self-Employed', 'Unemployed', 'Project Based']
CITIES = ['San Pablo City', 'Santa Cruz', 'Calamba', 'Los Baños', 'Biñan', 'Cabuyao', 'Nagcarlan', 'Liliw', 'Siniloan', 'Lucena', 'Lipa', 'Imus', 'Antipolo', 'Quezon City']
PROV = ['Laguna', 'Laguna', 'Laguna', 'Quezon', 'Batangas', 'Cavite', 'Rizal', 'Metro Manila']
ROLES = ['Software Developer', 'Accountant', 'Teacher', 'Nurse', 'Sales Associate', 'Customer Service Rep', 'Encoder', 'Admin Assistant', 'Engineer', 'Technician', 'Bank Teller', 'HR Officer']

ap = argparse.ArgumentParser()
ap.add_argument('--rows', type=int, required=True)
ap.add_argument('--tag', default='x')
ap.add_argument('--seed', type=int, default=1)
ap.add_argument('--out', required=True)
a = ap.parse_args()
rnd = random.Random(a.seed)

wb = openpyxl.Workbook(write_only=True)
ws = wb.create_sheet('Graduates')
ws.append(['LAGUNA STATE POLYTECHNIC UNIVERSITY - Data on Employment for Updating (SY 2022-2023)'] + [None] * 11)
ws.append(['No.', 'Name of Graduates', 'Program Name', 'Gender', 'Date of Graduation', 'Status of Employment After College', 'Sector',
           'Company & Position', 'Location of Employment', 'E-mail', 'Contact Number', 'Home Address'])
ws.append([None] * 12)
sfx = lambda: ''.join(rnd.choice('ABCDEFGHJKLMNPRSTUVWXYZ') for _ in range(2))
for i in range(1, a.rows + 1):
    last = rnd.choice(LAST) + '-' + sfx()
    first = rnd.choice(FIRST)
    st = rnd.choice(STATUS)
    company = '' if st == 'Unemployed' else f'Company {rnd.randint(1, 3000)} Corp. / {rnd.choice(ROLES)}'
    email = f'{a.tag}.{i}@import.test' if rnd.random() < 0.6 else ''
    ws.append([i, f'{last.upper()}, {first.upper()} {chr(65 + rnd.randint(0, 25))}.', rnd.choice(PROGRAMS),
               rnd.choice(['Male', 'Female']), f'June {rnd.randint(1, 28)}, {rnd.randint(2015, 2025)}', st,
               rnd.choice(['Private', 'Government']), company, rnd.choice(['Local', 'Local', 'Local', 'Abroad']),
               email, f'0917{rnd.randint(0, 9999999):07d}', f'{rnd.choice(CITIES)}, {rnd.choice(PROV)}'])
wb.save(a.out)
print(a.out, a.rows, 'rows')
