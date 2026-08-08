#!/usr/bin/env python3
"""Extrait la liste des stations reelles desservies par chaque ligne RER (A-E) depuis le GTFS complet."""
import csv
from pathlib import Path
from collections import defaultdict

GTFS_DIR = Path(r"C:\Users\Jujusilb\Documents\BADE DE DONNEE\PARIS\metro ratp\symfony\site\metroratp\documentation\IDFM-gtfs")
OUT_DIR = Path(r"C:\Users\Jujusilb\AppData\Local\Temp\claude\C--Users-Jujusilb-Documents-BADE-DE-DONNEE-PARIS-metro-ratp-symfony-site-metroratp\3a92a524-ed2c-4362-bda2-4f0b9916cc58\scratchpad")

ROUTE_IDS = {
    "IDFM:C01742": "A",
    "IDFM:C01743": "B",
    "IDFM:C01727": "C",
    "IDFM:C01728": "D",
    "IDFM:C01729": "E",
}

print("Lecture stops.txt...")
stop_names = {}
stop_parent = {}
with open(GTFS_DIR / "stops.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        stop_names[row["stop_id"]] = row["stop_name"]
        stop_parent[row["stop_id"]] = row.get("parent_station", "")

print("Lecture trips.txt...")
trip_route = {}
with open(GTFS_DIR / "trips.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        if row["route_id"] in ROUTE_IDS:
            trip_route[row["trip_id"]] = ROUTE_IDS[row["route_id"]]

print(f"{len(trip_route)} trips RER trouves")

print("Lecture stop_times.txt (fichier volumineux, patience)...")
stations_par_ligne = defaultdict(set)
with open(GTFS_DIR / "stop_times.txt", encoding="utf-8") as f:
    reader = csv.DictReader(f)
    for row in reader:
        ligne = trip_route.get(row["trip_id"])
        if ligne is None:
            continue
        stop_id = row["stop_id"]
        name = stop_names.get(stop_id, "???")
        stations_par_ligne[ligne].add(name)

for ligne in "ABCDE":
    stations = sorted(stations_par_ligne[ligne])
    print(f"\n=== RER {ligne} : {len(stations)} stations ===")
    for s in stations:
        print(f"  {s}")

with open(OUT_DIR / "stations_rer.csv", "w", encoding="utf-8", newline="") as f:
    writer = csv.writer(f)
    writer.writerow(["ligne", "station"])
    for ligne in "ABCDE":
        for s in sorted(stations_par_ligne[ligne]):
            writer.writerow([ligne, s])

print("\nEcrit dans stations_rer.csv")
