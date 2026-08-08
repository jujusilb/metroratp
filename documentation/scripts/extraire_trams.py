#!/usr/bin/env python3
"""Extrait l'ordre reel des stations pour toutes les lignes de tramway (T1-T14) depuis le GTFS complet."""
import csv
from pathlib import Path
from collections import defaultdict

GTFS_DIR = Path(r"C:\Users\Jujusilb\Documents\BADE DE DONNEE\PARIS\metro ratp\symfony\site\metroratp\documentation\IDFM-gtfs")
IDFM_DIR = Path(r"C:\Users\Jujusilb\Documents\BADE DE DONNEE\PARIS\idfmobilite\csv")

ROUTE_IDS = {
    "IDFM:C01389": "T1", "IDFM:C01390": "T2", "IDFM:C01391": "T3a", "IDFM:C01679": "T3b",
    "IDFM:C01843": "T4", "IDFM:C01684": "T5", "IDFM:C01794": "T6", "IDFM:C01774": "T7",
    "IDFM:C01795": "T8", "IDFM:C02317": "T9", "IDFM:C02528": "T10", "IDFM:C01999": "T11",
    "IDFM:C02529": "T12", "IDFM:C02344": "T13", "IDFM:C02732": "T14",
}

print("Lecture zones-de-correspondance.csv (noms officiels ZdC, coherents avec la base)...")
zdc_name = {}
with open(IDFM_DIR / "zones-de-correspondance.csv", encoding="utf-8-sig") as f:
    for row in csv.DictReader(f, delimiter=";"):
        zdc_name[row["ZdCId"]] = row["ZdCName"]

print("Lecture stops.txt (stop_id -> nom ZdC)...")
stop_names = {}
with open(GTFS_DIR / "stops.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        parent = row.get("parent_station", "")
        if parent:
            zdc_id = parent.replace("IDFM:", "")
            if zdc_id in zdc_name:
                stop_names[row["stop_id"]] = zdc_name[zdc_id]

print("Lecture trips.txt...")
trip_route_dir = {}  # trip_id -> (route_id, direction_id, headsign)
with open(GTFS_DIR / "trips.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        if row["route_id"] in ROUTE_IDS:
            trip_route_dir[row["trip_id"]] = (row["route_id"], row["direction_id"], row.get("trip_headsign", ""))

print(f"{len(trip_route_dir)} trips retenus au total")

print("\nLecture stop_times.txt (garde tous les trips retenus, choisit le plus long ensuite)...")
stops_by_trip = defaultdict(list)
with open(GTFS_DIR / "stop_times.txt", encoding="utf-8") as f:
    reader = csv.DictReader(f)
    for row in reader:
        if row["trip_id"] in trip_route_dir:
            stops_by_trip[row["trip_id"]].append((int(row["stop_sequence"]), row["stop_id"]))

# Pour chaque (route, direction), garder le trip le plus long (le plus d'arrets = trajet complet,
# pas un service partiel/court).
meilleur_trip = {}  # (route_id, direction_id) -> trip_id
for trip_id, stops in stops_by_trip.items():
    route_id, direction_id, _ = trip_route_dir[trip_id]
    cle = (route_id, direction_id)
    if cle not in meilleur_trip or len(stops) > len(stops_by_trip[meilleur_trip[cle]]):
        meilleur_trip[cle] = trip_id

for route_id, label in ROUTE_IDS.items():
    print(f"\n=== {label} ===")
    for direction_id in ("0", "1"):
        trip_id = meilleur_trip.get((route_id, direction_id))
        if trip_id is None:
            continue
        stops = sorted(stops_by_trip[trip_id])
        headsign = trip_route_dir[trip_id][2]
        print(f"  direction {direction_id} (headsign: {headsign}, {len(stops)} arrets):")
        for seq, sid in stops:
            print(f"    {seq}: {stop_names.get(sid, '???')}")
