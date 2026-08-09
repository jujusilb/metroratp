import csv
import sys
from collections import defaultdict

sys.stdout.reconfigure(encoding='utf-8')

degre = defaultdict(lambda: defaultdict(int))
noms = {}

with open('documentation/scripts/donnees-extraites/troncons_rer.csv', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        r = row['route_label']
        a, b = row['zdc_a'], row['zdc_b']
        noms[a] = row['nom_a']
        noms[b] = row['nom_b']
        degre[r][a] += 1
        degre[r][b] += 1

for r in ('A', 'B', 'D', 'E'):
    print(f'--- Ligne {r} ---')
    print('Terminus (degre 1):', sorted(noms[s] for s, d in degre[r].items() if d == 1))
    print('Embranchements (degre 3+):', sorted((noms[s], d) for s, d in degre[r].items() if d >= 3))
    print('Total stations:', len(degre[r]))
    print()
