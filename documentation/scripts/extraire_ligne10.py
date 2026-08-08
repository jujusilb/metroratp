#!/usr/bin/env python3
"""Extrait l'ordre reel des stations de la ligne 10 depuis le GTFS complet (pas seulement le cache filtre)."""
import csv
from pathlib import Path
from collections import defaultdict

GTFS_DIR = Path(r"C:\Users\Jujusilb\Documents\BADE DE DONNEE\PARIS\metro ratp\symfony\site\metroratp\documentation\IDFM-gtfs")
ROUTE_ID = "IDFM:C01380"

stop_names = {}
with open(GTFS_DIR / "stops.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        stop_names[row["stop_id"]] = row["stop_name"]

print("Lecture trips.txt...")
trips_for_route = []
with open(GTFS_DIR / "trips.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        if row["route_id"] == ROUTE_ID:
            trips_for_route.append(row)

seen_directions = {}
for t in trips_for_route:
    d = t["direction_id"]
    if d not in seen_directions:
        seen_directions[d] = t["trip_id"]
print("Directions trouvees:", seen_directions)

wanted_trip_ids = set(seen_directions.values())
trip_headsign = {t["trip_id"]: t.get("trip_headsign", "") for t in trips_for_route if t["trip_id"] in wanted_trip_ids}

stops_by_trip = defaultdict(list)
with open(GTFS_DIR / "stop_times.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        if row["trip_id"] in wanted_trip_ids:
            stops_by_trip[row["trip_id"]].append((int(row["stop_sequence"]), row["stop_id"]))

for trip_id, stops in stops_by_trip.items():
    stops.sort()
    print(f"\n--- direction {trip_id}, headsign: {trip_headsign.get(trip_id)} ---")
    for seq, sid in stops:
        print(f"  {seq}: {stop_names.get(sid, '???')} ({sid})")
