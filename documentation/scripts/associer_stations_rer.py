# -*- coding: utf-8 -*-
"""
Associe les Station existantes (creees par ImporterLignesRerCommand, sans codeExterne) aux
ZdCId extraits du GTFS (troncons_rer.csv), en ne comparant les noms QUE dans le cadre etroit
d'une meme ligne RER (26 a 59 stations par ligne) plutot que sur l'ensemble des ~14000 stations
nationales : c'est exactement ce qui avait cause la corruption de donnees lors du premier import
reseau complet (voir ImporterReseauCompletCommand, docblock). Ici le rapprochement se fait sur un
perimetre deja connu et restreint (les dessertes de CETTE ligne), donc sans risque de collision
avec un lieu homonyme sans rapport ailleurs en France.

Sortie : documentation/scripts/donnees-extraites/association_stations_rer.csv
Colonnes : route_label,station_id,zdc_id,db_label,gtfs_label
Affiche aussi les noms non apparies des deux cotes, pour verification manuelle avant tout import.
"""
import csv
import re
import sys
import unicodedata

import pymysql

sys.stdout.reconfigure(encoding='utf-8')

LIGNE_ID_PAR_ROUTE = {'A': 19, 'B': 20, 'D': 22, 'E': 23}
SORTIE = 'documentation/scripts/donnees-extraites/association_stations_rer.csv'

# Paires manuelles : memes lieux reels, noms trop differents pour la normalisation automatique
# (abreviation "CDG" vs "Charles de Gaulle", suffixe "- RER"/"TGV"). Verifie une par une.
ASSOCIATIONS_MANUELLES = {
    'B': [
        (370, 'Aéroport CDG 1 (Terminal 3) - RER', '73596', 'Aéroport CDG 1 (Terminal 3)'),
        (371, 'Aéroport Charles de Gaulle 2 (Terminal 2)', '73699', 'Aéroport CDG - Terminal 2 (TGV)'),
    ],
}


def normaliser(texte):
    texte = unicodedata.normalize('NFKD', texte).encode('ascii', 'ignore').decode('ascii')
    texte = texte.lower()
    texte = re.sub(r'[^a-z0-9]+', ' ', texte)
    return texte.strip()


def charger_troncons_par_route():
    noms_par_route = {}
    with open('documentation/scripts/donnees-extraites/troncons_rer.csv', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            r = row['route_label']
            noms_par_route.setdefault(r, {})
            noms_par_route[r][row['zdc_a']] = row['nom_a']
            noms_par_route[r][row['zdc_b']] = row['nom_b']
    return noms_par_route


def main():
    noms_par_route = charger_troncons_par_route()

    conn = pymysql.connect(host='127.0.0.1', user='root', password='', database='metroratp', charset='utf8mb4')
    cur = conn.cursor()

    lignes_association = []

    for route_label, ligne_id in LIGNE_ID_PAR_ROUTE.items():
        cur.execute(
            "SELECT DISTINCT s.id, s.label FROM desserte d JOIN station s ON s.id = d.station_id WHERE d.ligne_id = %s",
            (ligne_id,),
        )
        stations_db = cur.fetchall()  # [(id, label), ...]
        db_par_norm = {}
        for sid, label in stations_db:
            db_par_norm.setdefault(normaliser(label), []).append((sid, label))

        gtfs_noms = noms_par_route.get(route_label, {})
        gtfs_par_norm = {}
        for zdc, nom in gtfs_noms.items():
            gtfs_par_norm.setdefault(normaliser(nom), []).append((zdc, nom))

        normes_db = set(db_par_norm)
        normes_gtfs = set(gtfs_par_norm)
        communs = normes_db & normes_gtfs

        print(f"=== {route_label} : {len(stations_db)} stations DB, {len(gtfs_noms)} stations GTFS, {len(communs)} appariees ===")

        for norm in communs:
            db_liste = db_par_norm[norm]
            gtfs_liste = gtfs_par_norm[norm]
            if len(db_liste) == 1 and len(gtfs_liste) == 1:
                sid, db_label = db_liste[0]
                zdc, gtfs_label = gtfs_liste[0]
                lignes_association.append((route_label, sid, zdc, db_label, gtfs_label))
            else:
                print(f"  AMBIGU '{norm}': DB={db_liste} GTFS={gtfs_liste}")

        for sid, db_label, zdc, gtfs_label in ASSOCIATIONS_MANUELLES.get(route_label, []):
            lignes_association.append((route_label, sid, zdc, db_label, gtfs_label))
            normes_db.discard(normaliser(db_label))
            normes_gtfs.discard(normaliser(gtfs_label))

        non_apparies_db = normes_db - normes_gtfs
        non_apparies_gtfs = normes_gtfs - normes_db
        if non_apparies_db:
            print(f"  DB sans correspondance GTFS: {[db_par_norm[n] for n in non_apparies_db]}")
        if non_apparies_gtfs:
            print(f"  GTFS sans correspondance DB: {[gtfs_par_norm[n] for n in non_apparies_gtfs]}")
        print()

    conn.close()

    with open(SORTIE, 'w', newline='', encoding='utf-8') as f:
        writer = csv.writer(f)
        writer.writerow(['route_label', 'station_id', 'zdc_id', 'db_label', 'gtfs_label'])
        writer.writerows(lignes_association)

    print(f"{len(lignes_association)} associations ecrites dans {SORTIE}")


if __name__ == '__main__':
    main()
