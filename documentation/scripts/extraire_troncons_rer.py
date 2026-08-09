# -*- coding: utf-8 -*-
"""
Extrait le graphe physique (troncons = paires de stations adjacentes) des lignes RER
A/B/D/E depuis le GTFS IDFM, en resolvant chaque stop_id vers son ZdCId (zone de
correspondance = "station" cote base de donnees) via parent_station.

Contrairement aux trams (ou trip_headsign donnait un vrai nom de destination), le GTFS RER
utilise des "codes mission" SNCF/RATP (4 lettres, ex: TAXE, UZAR, DECA) comme headsign :
inexploitable pour regrouper des trajets par branche. Approche retenue a la place : construire
directement l'union de toutes les paires de stations consecutives (station_i, station_i+1)
observees dans TOUS les passages de la ligne, ce qui donne le graphe physique complet (troncons)
sans avoir besoin de deviner les branches a partir du texte des headsigns. La structure en arbre
(troncs/branches/terminus) emerge d'elle-meme cote PHP en analysant le degre de chaque station
dans ce graphe (1 voisin = terminus, 2 = tronc, 3+ = embranchement).

Sortie : documentation/scripts/donnees-extraites/troncons_rer.csv
Colonnes : route_label,zdc_a,zdc_b,nom_a,nom_b,duree_mediane_secondes,nb_observations

ATTENTION : l'union brute des paires consecutives cree aussi de FAUSSES adjacences a cause des
trains express/semi-directs qui sautent des gares (ex: un Gare de Lyon -> Villeneuve-Saint-Georges
direct alors que les trains omnibus s'arretent a Maisons-Alfort entre les deux). Une premiere
version sans filtrage donnait des "embranchements" a 5-6 voisins la ou la vraie ligne n'en a que
2-3 : ce n'est pas un vrai embranchement, juste un raccourci d'express.

Un premier essai de reduction "retire l'arete la moins observee d'abord" s'est revele faux :
sur le RER A, les missions rapides qui sautent Nation entre Gare de Lyon et Vincennes sont EN FAIT
plus nombreuses aux heures de pointe (4056 observations) que les missions omnibus qui s'y arretent
(629) — l'arete reelle (Nation-Vincennes) a fini retiree a la place du raccourci. Le nombre
d'observations n'est donc pas un signal fiable. A la place : reduction geometrique, en utilisant
les coordonnees reelles (ZdCXEpsg2154/ZdCYEpsg2154) du referentiel IDFM. Une arete (a,b) est un
raccourci s'il existe une station x (avec des aretes a-x et x-b deja retenues) telle que
distance(a,x) + distance(x,b) est proche de distance(a,b) : x est alors "sur le chemin" entre a et
b, donc l'arete directe ne fait que sauter x plutot que representer une vraie voie separee.
"""
import csv
import math
import statistics
from collections import defaultdict

GTFS_DIR = 'documentation/IDFM-gtfs/'
REFERENTIEL_DIR = 'C:/Users/Jujusilb/Documents/BADE DE DONNEE/PARIS/idfmobilite/csv/'
SORTIE = 'documentation/scripts/donnees-extraites/troncons_rer.csv'

ROUTES = {
    'IDFM:C01742': 'A',
    'IDFM:C01743': 'B',
    'IDFM:C01728': 'D',
    'IDFM:C01729': 'E',
}


def charger_coordonnees_zdc():
    """ZdCId -> (x, y) en coordonnees projetees Lambert-93 (EPSG:2154, en metres)."""
    coords = {}
    with open(REFERENTIEL_DIR + 'zones-de-correspondance.csv', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f, delimiter=';')
        for row in reader:
            try:
                coords[row['ZdCId']] = (float(row['ZdCXEpsg2154']), float(row['ZdCYEpsg2154']))
            except (ValueError, KeyError):
                pass
    return coords


def charger_zdc_par_stop():
    """stop_id GTFS -> (ZdCId, nom) via parent_station."""
    zdc_noms = {}
    with open(REFERENTIEL_DIR + 'zones-de-correspondance.csv', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f, delimiter=';')
        for row in reader:
            zdc_noms[row['ZdCId']] = row['ZdCName']

    stop_vers_zdc = {}
    with open(GTFS_DIR + 'stops.txt', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            parent = row.get('parent_station', '')
            if parent.startswith('IDFM:'):
                zdc_id = parent.removeprefix('IDFM:')
                if zdc_id in zdc_noms:
                    stop_vers_zdc[row['stop_id']] = (zdc_id, zdc_noms[zdc_id])
    return stop_vers_zdc


def charger_trips_par_route():
    """route_id -> liste de trip_id."""
    trips = defaultdict(list)
    with open(GTFS_DIR + 'trips.txt', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            if row['route_id'] in ROUTES:
                trips[row['route_id']].append(row['trip_id'])
    return trips


def main():
    coords = charger_coordonnees_zdc()
    stop_vers_zdc = charger_zdc_par_stop()
    trips_par_route = charger_trips_par_route()

    trip_ids_interessants = set()
    for liste in trips_par_route.values():
        trip_ids_interessants.update(liste)

    # trip_id -> route_id (pour retrouver le label a l'ecriture)
    trip_vers_route = {}
    for route_id, liste in trips_par_route.items():
        for t in liste:
            trip_vers_route[t] = route_id

    # trip_id -> liste de (stop_sequence, zdc_id, nom)
    arrets_par_trip = defaultdict(list)
    with open(GTFS_DIR + 'stop_times.txt', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            tid = row['trip_id']
            if tid not in trip_ids_interessants:
                continue
            stop_id = row['stop_id']
            if stop_id not in stop_vers_zdc:
                continue
            zdc_id, nom = stop_vers_zdc[stop_id]
            arrets_par_trip[tid].append((
                int(row['stop_sequence']),
                zdc_id,
                nom,
                row['arrival_time'],
                row['departure_time'],
            ))

    def to_secondes(hms):
        h, m, s = hms.split(':')
        return int(h) * 3600 + int(m) * 60 + int(s)

    # cle (route_label, frozenset({zdc_a, zdc_b})) -> liste de durees observees (secondes)
    troncons = defaultdict(list)
    noms = {}

    for tid, arrets in arrets_par_trip.items():
        arrets.sort(key=lambda a: a[0])
        route_label = ROUTES[trip_vers_route[tid]]
        for i in range(len(arrets) - 1):
            _, zdc_a, nom_a, _, dep_a = arrets[i]
            _, zdc_b, nom_b, arr_b, _ = arrets[i + 1]
            if zdc_a == zdc_b:
                continue  # doublon (2 quais de la meme station consecutifs)
            noms[zdc_a] = nom_a
            noms[zdc_b] = nom_b
            try:
                duree = to_secondes(arr_b) - to_secondes(dep_a)
            except ValueError:
                duree = None
            cle = (route_label, frozenset({zdc_a, zdc_b}))
            if duree is not None and 0 < duree < 1800:
                troncons[cle].append(duree)
            elif cle not in troncons:
                troncons[cle] = []

    def distance(a, b):
        if a not in coords or b not in coords:
            return None
        (xa, ya), (xb, yb) = coords[a], coords[b]
        return math.hypot(xb - xa, yb - ya)

    def plus_court_chemin_sans_arete_directe(adj, poids, a, b):
        """Dijkstra entre a et b sur le graphe courant, en ignorant l'arete directe a-b :
        detecte aussi les raccourcis qui sautent PLUSIEURS stations d'affilee (pas seulement
        une), contrairement a une simple recherche de voisin commun."""
        distances = {a: 0.0}
        a_traiter = [(0.0, a)]
        while a_traiter:
            a_traiter.sort()
            d, courant = a_traiter.pop(0)
            if courant == b:
                return d
            if d > distances.get(courant, math.inf):
                continue
            for voisin in adj[courant]:
                if courant == a and voisin == b:
                    continue  # l'arete directe elle-meme, exclue de cette recherche
                p = poids.get((courant, voisin)) or poids.get((voisin, courant))
                if p is None:
                    continue
                nd = d + p
                if nd < distances.get(voisin, math.inf):
                    distances[voisin] = nd
                    a_traiter.append((nd, voisin))
        return None

    # Reduction geometrique, ligne par ligne : retire une arete (a,b) si le plus court chemin
    # alternatif (sans cette arete, via d'autres stations reelles) fait a peu pres la meme
    # distance physique (marge de 25% : certaines voies (ex: Gare du Nord -> Aulnay sur le B)
    # courbent assez pour que la somme des sauts reels depasse 20% de la distance a vol
    # d'oiseau ; verifie manuellement sur ce cas precis avant d'elargir la marge). Dans ce
    # cas l'arete directe n'est qu'un raccourci d'express qui saute une ou plusieurs stations,
    # pas une vraie voie separee. On traite les aretes les plus longues en premier (les plus
    # susceptibles d'etre des raccourcis couvrant plusieurs stations).
    troncons_par_ligne = defaultdict(dict)
    for (route_label, paire), durees in troncons.items():
        troncons_par_ligne[route_label][paire] = durees

    troncons_retenus = {}
    for route_label, aretes in troncons_par_ligne.items():
        adj = defaultdict(set)
        poids = {}
        for paire in aretes:
            a, b = tuple(paire)
            d = distance(a, b) or 0.0
            adj[a].add(b)
            adj[b].add(a)
            poids[(a, b)] = d
            poids[(b, a)] = d

        ordre = sorted(aretes.keys(), key=lambda p: -(distance(*tuple(p)) or 0))
        for paire in ordre:
            a, b = tuple(paire)
            dist_directe = distance(a, b)
            alt = plus_court_chemin_sans_arete_directe(adj, poids, a, b) if dist_directe is not None else None
            if alt is not None and alt <= dist_directe * 1.25:
                adj[a].discard(b)
                adj[b].discard(a)
            else:
                troncons_retenus[(route_label, paire)] = aretes[paire]

    with open(SORTIE, 'w', newline='', encoding='utf-8') as f:
        writer = csv.writer(f)
        writer.writerow(['route_label', 'zdc_a', 'zdc_b', 'nom_a', 'nom_b', 'duree_mediane_secondes', 'nb_observations'])
        for (route_label, paire), durees in sorted(troncons_retenus.items(), key=lambda kv: (kv[0][0],)):
            zdc_a, zdc_b = sorted(paire)
            mediane = round(statistics.median(durees)) if durees else ''
            writer.writerow([route_label, zdc_a, zdc_b, noms[zdc_a], noms[zdc_b], mediane, len(durees)])

    print(f"{len(troncons_retenus)} troncons retenus (sur {len(troncons)} aretes brutes) ecrits dans {SORTIE}")
    for label in ('A', 'B', 'D', 'E'):
        nb_brut = sum(1 for (r, _) in troncons if r == label)
        nb_retenu = sum(1 for (r, _) in troncons_retenus if r == label)
        print(f"  {label}: {nb_retenu} troncons retenus (etaient {nb_brut} avant reduction)")


if __name__ == '__main__':
    main()
