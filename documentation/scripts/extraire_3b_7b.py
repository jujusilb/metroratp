#!/usr/bin/env python3
"""Extrait l'ordre reel des stations pour les lignes 3B et 7B a partir du GTFS."""
import csv
from pathlib import Path
from collections import defaultdict

GTFS_DIR = Path(r"C:\Users\Jujusilb\Documents\BADE DE DONNEE\PARIS\metro ratp\symfony\site\metroratp\documentation\IDFM-gtfs")
CACHE_DIR = Path(r"C:\Users\Jujusilb\AppData\Local\Temp\gtfs-idfm")

ROUTE_IDS = {"IDFM:C01386": "3B", "IDFM:C01387": "7B"}

# stop_id -> stop_name
stop_names = {}
with open(CACHE_DIR / "stops_full.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        stop_names[row["stop_id"]] = row["stop_name"]

# trip_id -> (route_id, direction_id, trip_headsign)
print("Lecture trips.txt...")
trips_for_route = defaultdict(list)
with open(GTFS_DIR / "trips.txt", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        if row["route_id"] in ROUTE_IDS:
            trips_for_route[row["route_id"]].append(row)

for route_id, label in ROUTE_IDS.items():
    trips = trips_for_route[route_id]
    print(f"\n=== Ligne {label} ({route_id}) : {len(trips)} trips ===")
    # un trip par direction_id, pour voir les deux sens
    seen_directions = {}
    for t in trips:
        d = t["direction_id"]
        if d not in seen_directions:
            seen_directions[d] = t["trip_id"]
    print("Directions trouvees:", seen_directions)

# maintenant recuperer la sequence d'arrets pour un trip de chaque direction
print("\nLecture metro_stop_times.csv pour ces trips...")
wanted_trip_ids = set()
trip_direction = {}
for route_id, label in ROUTE_IDS.items():
    trips = trips_for_route[route_id]
    seen_directions = {}
    for t in trips:
        d = t["direction_id"]
        if d not in seen_directions:
            seen_directions[d] = t["trip_id"]
            wanted_trip_ids.add(t["trip_id"])
            trip_direction[t["trip_id"]] = (label, d, t.get("trip_headsign", ""))

stops_by_trip = defaultdict(list)
with open(CACHE_DIR / "metro_stop_times.csv", encoding="utf-8", newline="") as f:
    for row in csv.reader(f):
        trip_id, _arr, _dep, stop_id, stop_sequence = row
        if trip_id in wanted_trip_ids:
            stops_by_trip[trip_id].append((int(stop_sequence), stop_id))

for trip_id, stops in stops_by_trip.items():
    label, d, headsign = trip_direction[trip_id]
    stops.sort()
    print(f"\n--- Ligne {label}, direction {d} (headsign: {headsign}) ---")
    for seq, sid in stops:
        print(f"  {seq}: {stop_names.get(sid, '???')} ({sid})")
