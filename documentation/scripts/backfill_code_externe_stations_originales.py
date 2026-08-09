# -*- coding: utf-8 -*-
"""
Relie les stations Metro/Tramway/RER "originales" (creees a la main tot dans le projet, avant
l'import du reseau complet IDFM) a leur ZdCId officiel, par correspondance de nom au sein du
referentiel zones-de-correspondance.csv. Perimetre volontairement restreint (~240 stations mode
lourd sans codeExterne) : jamais de correspondance sur l'ensemble des ~14000 stations nationales
(voir l'incident documente dans ImporterReseauCompletCommand).

Necessite une station DB deja backfillee ET un ZdC MATCHE de facon UNIQUE (1 station DB avec ce
nom normalise, 1 ZdC avec ce nom normalise) pour etre retenue ; sinon affichee pour revue
manuelle, jamais devinee.

Sortie : documentation/scripts/donnees-extraites/backfill_code_externe.sql (a relire avant
execution).
"""
import csv
import re
import sys
import unicodedata

import pymysql

sys.stdout.reconfigure(encoding='utf-8')

REFERENTIEL_DIR = 'C:/Users/Jujusilb/Documents/BADE DE DONNEE/PARIS/idfmobilite/csv/'
SORTIE_SQL = 'documentation/scripts/donnees-extraites/backfill_code_externe.sql'


def normaliser(texte):
    texte = unicodedata.normalize('NFKD', texte).encode('ascii', 'ignore').decode('ascii')
    texte = texte.lower()
    texte = re.sub(r'[^a-z0-9]+', ' ', texte)
    return texte.strip()


def main():
    zdc_par_nom = {}
    with open(REFERENTIEL_DIR + 'zones-de-correspondance.csv', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f, delimiter=';')
        for row in reader:
            zdc_par_nom.setdefault(normaliser(row['ZdCName']), []).append((row['ZdCId'], row['ZdCName']))

    conn = pymysql.connect(host='127.0.0.1', user='root', password='', database='metroratp', charset='utf8mb4')
    cur = conn.cursor()
    cur.execute("""
        SELECT DISTINCT s.id, s.label
        FROM station s
        JOIN desserte d ON d.station_id = s.id
        JOIN ligne l ON l.id = d.ligne_id
        JOIN type_transport tt ON tt.id = l.type_transport_id
        WHERE tt.label IN ('Métro','Tramway','RER') AND s.code_externe IS NULL
        ORDER BY s.label
    """)
    stations = cur.fetchall()

    # ZdC deja utilises par une AUTRE station existante (le doublon cree par l'import du reseau
    # complet, qui lui a deja son codeExterne) : les affecter en plus violerait la contrainte
    # unique code_externe. Ce ne sont pas des "non trouves", ce sont de vrais doublons a fusionner
    # plus tard (hors perimetre de ce script).
    cur.execute("SELECT code_externe FROM station WHERE code_externe IS NOT NULL")
    zdc_deja_en_base = {row[0] for row in cur.fetchall()}
    conn.close()

    updates = []
    non_trouves = []
    ambigus = []
    doublons_reels = []

    # Une station DB peut, apres normalisation, matcher plusieurs ZdC (ex: meme nom, villes
    # differentes) : dans ce cas ambigu, on ignore plutot que deviner.
    zdc_deja_utilises = set(zdc_deja_en_base)

    for station_id, label in stations:
        norm = normaliser(label)
        candidats = zdc_par_nom.get(norm, [])
        candidats_libres = [c for c in candidats if c[0] not in zdc_deja_utilises]

        if 1 == len(candidats_libres):
            zdc_id, zdc_nom = candidats_libres[0]
            updates.append((station_id, label, zdc_id, zdc_nom))
            zdc_deja_utilises.add(zdc_id)
        elif 0 == len(candidats_libres):
            if any(c[0] in zdc_deja_en_base for c in candidats):
                doublons_reels.append((station_id, label, [c for c in candidats if c[0] in zdc_deja_en_base]))
            else:
                non_trouves.append((station_id, label))
        else:
            ambigus.append((station_id, label, candidats_libres))

    print(f"{len(updates)} stations a mettre a jour (match unique, ZdC libre)")
    print(f"{len(doublons_reels)} vrais doublons (ZdC deja utilise par une autre Station existante)")
    print(f"{len(non_trouves)} stations sans ZdC correspondant")
    print(f"{len(ambigus)} stations ambigues (plusieurs ZdC possibles)\n")

    if doublons_reels:
        print("--- Vrais doublons (deja une autre Station avec ce ZdC, non traites ici) ---")
        for sid, label, candidats in doublons_reels:
            print(f"  #{sid} {label} -> {candidats}")
        print()

    if non_trouves:
        print("--- Sans correspondance ---")
        for sid, label in non_trouves:
            print(f"  #{sid} {label}")
        print()

    if ambigus:
        print("--- Ambigues (ignorees) ---")
        for sid, label, candidats in ambigus:
            print(f"  #{sid} {label} -> {candidats}")
        print()

    with open(SORTIE_SQL, 'w', encoding='utf-8') as f:
        f.write("-- Backfill code_externe pour les stations originales Metro/Tramway/RER\n")
        f.write("-- Genere par documentation/scripts/backfill_code_externe_stations_originales.py\n")
        for station_id, label, zdc_id, zdc_nom in updates:
            f.write(f"UPDATE station SET code_externe='{zdc_id}' WHERE id={station_id}; -- {label} -> {zdc_nom}\n")

    print(f"SQL ecrit dans {SORTIE_SQL}")


if __name__ == '__main__':
    main()
