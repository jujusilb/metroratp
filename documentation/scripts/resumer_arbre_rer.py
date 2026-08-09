import csv
import sys
from collections import defaultdict

sys.stdout.reconfigure(encoding='utf-8')

adj = defaultdict(lambda: defaultdict(set))
noms = {}

with open('documentation/scripts/donnees-extraites/troncons_rer.csv', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        r = row['route_label']
        a, b = row['zdc_a'], row['zdc_b']
        noms[a] = row['nom_a']
        noms[b] = row['nom_b']
        adj[r][a].add(b)
        adj[r][b].add(a)


def marcher(r, depart, venant_de, chemin, visites):
    if depart in visites:
        print('  ' + ' -> '.join(chemin + [noms[depart]]) + '  [CYCLE DETECTE - retour sur ' + noms[depart] + ']')
        return
    visites = visites | {depart}
    chemin = chemin + [noms[depart]]
    voisins = [v for v in adj[r][depart] if v != venant_de]
    if len(voisins) == 1:
        return marcher(r, voisins[0], depart, chemin, visites)
    if len(voisins) == 0:
        print('  ' + ' -> '.join(chemin) + '  [TERMINUS]')
        return
    print('  ' + ' -> '.join(chemin) + f'  [EMBRANCHEMENT x{len(voisins)}]')
    for v in voisins:
        marcher(r, v, depart, [], visites)


for r in ('A', 'B', 'D', 'E'):
    print(f'=== Ligne {r} ===')
    nb_aretes = sum(len(vs) for vs in adj[r].values()) // 2
    nb_noeuds = len(adj[r])
    print(f'  ({nb_noeuds} stations, {nb_aretes} aretes -- un arbre en aurait {nb_noeuds - 1})')
    termini = [s for s, vs in adj[r].items() if len(vs) == 1]
    depart = termini[0]
    marcher(r, depart, None, [], set())
    print()
