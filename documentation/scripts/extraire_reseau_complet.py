#!/usr/bin/env python3
"""Extrait TOUTES les lignes (bus/car/tram/RER/metro) et leurs arrets reels (niveau ZdC =
zone de correspondance) depuis le GTFS complet IDFM + le referentiel officiel des lignes.

Jointure fiable par ID (pas de correspondance approximative par nom) :
  stop_times.stop_id -> stops.parent_station (= ZdCId) -> zones-de-correspondance.ZdCName
  stop_times.trip_id -> trips.route_id -> referentiel-des-lignes (nom/couleur/mode/operateur)

Exclut les 21 lignes deja importees (metro 1-14+3b+7b, RER A-E) pour ne pas les recreer.
"""
import csv
from pathlib import Path

GTFS_DIR = Path(r"C:\Users\Jujusilb\Documents\BADE DE DONNEE\PARIS\metro ratp\symfony\site\metroratp\documentation\IDFM-gtfs")
IDFM_DIR = Path(r"C:\Users\Jujusilb\Documents\BADE DE DONNEE\PARIS\idfmobilite\csv")
OUT_DIR = Path(r"C:\Users\Jujusilb\AppData\Local\Temp\claude\C--Users-Jujusilb-Documents-BADE-DE-DONNEE-PARIS-metro-ratp-symfony-site-metroratp\3a92a524-ed2c-4362-bda2-4f0b9916cc58\scratchpad")

DEJA_IMPORTEES = {
    "IDFM:C01371", "IDFM:C01372", "IDFM:C01373", "IDFM:C01384", "IDFM:C01386", "IDFM:C01387",
    "IDFM:C01377", "IDFM:C01383", "IDFM:C01374", "IDFM:C01375", "IDFM:C01376", "IDFM:C01378",
    "IDFM:C01379", "IDFM:C01380", "IDFM:C01382", "IDFM:C01381",  # metro 1-14 (+3b/7b deja dans C01386/87)
    "IDFM:C01742", "IDFM:C01743", "IDFM:C01727", "IDFM:C01728", "IDFM:C01729",  # RER A-E
}

print("Lecture zones-de-correspondance.csv...")
zdc_name = {}
with open(IDFM_DIR / "zones-de-correspondance.csv", encoding="utf-8-sig") as f:
    for row in csv.DictReader(f, delimiter=";"):
        zdc_name[row["ZdCId"]] = row["ZdCName"]
print(f"  {len(zdc_name)} zones de correspondance")

print("Lecture referentiel-des-lignes.csv...")
ref_ligne = {}
with open(IDFM_DIR / "referentiel-des-lignes.csv", encoding="utf-8-sig") as f:
    for row in csv.DictReader(f, delimiter=";"):
        if row.get("Status") != "active":
            continue
        ref_ligne[row["ID_Line"]] = {
            "nom": row["ShortName_Line"] or row["Name_Line"],
            "couleur": row["ColourWeb_hexa"] or "6c757d",
            "mode": row["TransportMode"],
            "submode": row["TransportSubmode"],
            "operateur": row["OperatorName"],
        }
print(f"  {len(ref_ligne)} lignes actives dans le referentiel")

print("Lecture stops.txt (stop_id -> ZdC)...")
stop_to_zdc = {}
with open(GTFS_DIR / "stops.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        parent = row.get("parent_station", "")
        if parent:
            zdc_id = parent.replace("IDFM:", "")
            if zdc_id in zdc_name:
                stop_to_zdc[row["stop_id"]] = zdc_id
print(f"  {len(stop_to_zdc)} arrets relies a une zone de correspondance connue")

print("Lecture trips.txt (trip_id -> route_id)...")
trip_to_route = {}
with open(GTFS_DIR / "trips.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        route_id = row["route_id"]
        if route_id in DEJA_IMPORTEES:
            continue
        route_id_court = route_id.replace("IDFM:", "")
        if route_id_court not in ref_ligne:
            continue
        trip_to_route[row["trip_id"]] = route_id_court
print(f"  {len(trip_to_route)} trips retenus (lignes actives, hors deja importees)")

print("Lecture stop_times.txt (fichier volumineux, plusieurs minutes)...")
paires = set()
n = 0
with open(GTFS_DIR / "stop_times.txt", encoding="utf-8") as f:
    reader = csv.reader(f)
    header = next(reader)
    idx_trip = header.index("trip_id")
    idx_stop = header.index("stop_id")
    for row in reader:
        n += 1
        if n % 2000000 == 0:
            print(f"  ... {n} lignes lues, {len(paires)} paires trouvees")
        route_id = trip_to_route.get(row[idx_trip])
        if route_id is None:
            continue
        zdc_id = stop_to_zdc.get(row[idx_stop])
        if zdc_id is None:
            continue
        paires.add((route_id, zdc_id))

print(f"Termine : {n} lignes lues, {len(paires)} paires (ligne, station) uniques")

print("Ecriture reseau_complet.csv...")
lignes_vues = set()
with open(OUT_DIR / "reseau_complet.csv", "w", encoding="utf-8", newline="") as f:
    writer = csv.writer(f)
    writer.writerow(["route_id", "ligne_nom", "couleur", "mode", "submode", "operateur", "zdc_id", "zdc_nom"])
    for route_id, zdc_id in sorted(paires):
        info = ref_ligne[route_id]
        writer.writerow([route_id, info["nom"], info["couleur"], info["mode"], info["submode"], info["operateur"], zdc_id, zdc_name[zdc_id]])
        lignes_vues.add(route_id)

print(f"Ecrit : {len(paires)} lignes, {len(lignes_vues)} lignes distinctes, {len(set(z for _, z in paires))} stations distinctes")
