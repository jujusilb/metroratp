import csv
import sys
from collections import defaultdict

sys.stdout.reconfigure(encoding='utf-8')

GTFS_DIR = 'documentation/IDFM-gtfs/'
REFERENTIEL_DIR = 'C:/Users/Jujusilb/Documents/BADE DE DONNEE/PARIS/idfmobilite/csv/'
ROUTES = {'IDFM:C01742': 'A'}

zdc_noms = {}
with open(REFERENTIEL_DIR + 'zones-de-correspondance.csv', encoding='utf-8-sig') as f:
    reader = csv.DictReader(f, delimiter=';')
    for row in reader:
        zdc_noms[row['ZdCId']] = row['ZdCName']

stop_vers_zdc = {}
stop_parent = {}
with open(GTFS_DIR + 'stops.txt', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        stop_parent[row['stop_id']] = row.get('parent_station', '')
        parent = row.get('parent_station', '')
        if parent.startswith('IDFM:'):
            zdc_id = parent.removeprefix('IDFM:')
            if zdc_id in zdc_noms:
                stop_vers_zdc[row['stop_id']] = (zdc_id, zdc_noms[zdc_id])

trip_route = {}
with open(GTFS_DIR + 'trips.txt', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        if row['route_id'] in ROUTES:
            trip_route[row['trip_id']] = ROUTES[row['route_id']]

arrets_brut = defaultdict(list)
with open(GTFS_DIR + 'stop_times.txt', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        tid = row['trip_id']
        if tid not in trip_route:
            continue
        arrets_brut[tid].append((int(row['stop_sequence']), row['stop_id']))

trouve = 0
for tid, l in arrets_brut.items():
    l.sort()
    seq_complete = [(seq, sid, stop_vers_zdc.get(sid)) for seq, sid in l]
    noms_filtres = [(seq, sid, zdc) for seq, sid, zdc in seq_complete if zdc]
    for i in range(len(noms_filtres) - 1):
        if noms_filtres[i][2][1] == 'Gare de Lyon' and noms_filtres[i + 1][2][1] == 'Vincennes':
            seq_i = noms_filtres[i][0]
            seq_j = noms_filtres[i + 1][0]
            entre = [(seq, sid, stop_parent.get(sid)) for seq, sid, zdc in seq_complete if seq_i < seq < seq_j]
            print('Trip', tid)
            print('Sequence complete (stop_id, parent_station brut) entre les deux :', entre)
            trouve += 1
            if trouve >= 3:
                raise SystemExit
