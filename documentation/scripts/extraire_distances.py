#!/usr/bin/env python3
"""
Calcule la distance reelle (en metres) entre stations consecutives du reseau metro,
a partir du GTFS IDFM (shapes.txt = trace geographique, stop_times.txt = ordre des arrets
par voyage). shapes.txt/stop_times.txt n'ont pas de colonne shape_dist_traveled ici : on la
recalcule nous-memes (somme haversine le long du trace, puis position de chaque arret =
point du trace le plus proche de ses coordonnees).

Sortie : troncon_distances.csv (stop_id_a,nom_a,stop_id_b,nom_b,distance_metres,nb_observations),
meme format que troncon_durees.csv pour reutiliser le meme pattern d'import cote PHP.
"""

import csv
import math
import sys
from pathlib import Path
from collections import defaultdict

GTFS_DIR = Path(r"C:\Users\Jujusilb\Documents\BADE DE DONNEE\PARIS\metro ratp\symfony\site\metroratp\documentation\IDFM-gtfs")
CACHE_DIR = Path(r"C:\Users\Jujusilb\AppData\Local\Temp\gtfs-idfm")
OUT_FILE = Path(__file__).parent / "troncon_distances.csv"


def haversine(lat1, lon1, lat2, lon2):
    r = 6371000.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dp = math.radians(lat2 - lat1)
    dl = math.radians(lon2 - lon1)
    a = math.sin(dp / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(math.sqrt(a))


def main():
    print("1/6 Lecture des trip_id metro...")
    with open(CACHE_DIR / "metro_trip_ids.txt", encoding="utf-8") as f:
        metro_trip_ids = set(line.strip() for line in f if line.strip())
    print(f"    {len(metro_trip_ids)} trip_id metro")

    print("2/6 Lecture de trips.txt (trip_id -> shape_id)...")
    trip_to_shape = {}
    with open(GTFS_DIR / "trips.txt", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            tid = row["trip_id"]
            if tid in metro_trip_ids:
                trip_to_shape[tid] = row["shape_id"]
    needed_shapes = set(trip_to_shape.values())
    print(f"    {len(trip_to_shape)} trips mappes, {len(needed_shapes)} shapes distincts necessaires")

    print("3/6 Lecture de shapes.txt (filtre sur les shapes necessaires)...")
    shape_points = defaultdict(list)
    with open(GTFS_DIR / "shapes.txt", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            sid = row["shape_id"]
            if sid in needed_shapes:
                shape_points[sid].append((
                    int(row["shape_pt_sequence"]),
                    float(row["shape_pt_lat"]),
                    float(row["shape_pt_lon"]),
                ))
    for sid in shape_points:
        shape_points[sid].sort(key=lambda p: p[0])
    print(f"    {len(shape_points)} shapes charges")

    print("4/6 Calcul des distances cumulees le long de chaque shape...")
    shape_cumdist = {}  # shape_id -> list of (lat, lon, cumdist)
    for sid, pts in shape_points.items():
        cum = 0.0
        out = [(pts[0][1], pts[0][2], 0.0)]
        for i in range(1, len(pts)):
            _, lat1, lon1 = pts[i - 1]
            _, lat2, lon2 = pts[i]
            cum += haversine(lat1, lon1, lat2, lon2)
            out.append((lat2, lon2, cum))
        shape_cumdist[sid] = out
    del shape_points

    print("5/6 Lecture des coordonnees des arrets (stops_full.txt)...")
    stop_coords = {}
    stop_names = {}
    with open(CACHE_DIR / "stops_full.txt", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            sid = row["stop_id"]
            try:
                stop_coords[sid] = (float(row["stop_lat"]), float(row["stop_lon"]))
                stop_names[sid] = row["stop_name"]
            except (ValueError, KeyError):
                continue
    print(f"    {len(stop_coords)} arrets avec coordonnees")

    print("6/6 Parcours des voyages metro (metro_stop_times.csv) et calcul des distances...")
    nearest_cache = {}  # (shape_id, stop_id) -> cumdist

    def position_sur_shape(shape_id, stop_id):
        key = (shape_id, stop_id)
        if key in nearest_cache:
            return nearest_cache[key]
        pts = shape_cumdist.get(shape_id)
        if not pts or stop_id not in stop_coords:
            nearest_cache[key] = None
            return None
        slat, slon = stop_coords[stop_id]
        best_d = None
        best_cum = None
        for lat, lon, cum in pts:
            # distance euclidienne en degres suffit pour trouver le plus proche (pas besoin
            # de haversine ici, on cherche juste l'argmin)
            d = (lat - slat) ** 2 + (lon - slon) ** 2
            if best_d is None or d < best_d:
                best_d = d
                best_cum = cum
        nearest_cache[key] = best_cum
        return best_cum

    # accumulateur : (stop_id_a, stop_id_b) -> [somme_distance, nb_observations]
    accum = defaultdict(lambda: [0.0, 0])

    current_trip = None
    current_stops = []  # list of (stop_sequence, stop_id)

    def traiter_voyage(trip_id, stops):
        if len(stops) < 2:
            return
        shape_id = trip_to_shape.get(trip_id)
        if not shape_id:
            return
        stops.sort(key=lambda s: s[0])
        cums = [position_sur_shape(shape_id, sid) for _, sid in stops]
        for i in range(len(stops) - 1):
            ca, cb = cums[i], cums[i + 1]
            if ca is None or cb is None:
                continue
            a_id = stops[i][1]
            b_id = stops[i + 1][1]
            dist = abs(cb - ca)
            if dist <= 0 or dist > 3000:
                # une distance nulle ou aberrante (>3km entre 2 stations metro consecutives)
                # indique un souci de matching sur le trace : on l'ignore plutot que de
                # polluer la moyenne.
                continue
            entry = accum[(a_id, b_id)]
            entry[0] += dist
            entry[1] += 1

    total_voyages = 0
    with open(CACHE_DIR / "metro_stop_times.csv", encoding="utf-8", newline="") as f:
        reader = csv.reader(f)
        for row in reader:
            trip_id, _arr, _dep, stop_id, stop_sequence = row
            if trip_id != current_trip:
                if current_trip is not None:
                    traiter_voyage(current_trip, current_stops)
                    total_voyages += 1
                    if total_voyages % 5000 == 0:
                        print(f"    ... {total_voyages} voyages traites")
                current_trip = trip_id
                current_stops = []
            current_stops.append((int(stop_sequence), stop_id))
        if current_trip is not None:
            traiter_voyage(current_trip, current_stops)
            total_voyages += 1

    print(f"    {total_voyages} voyages traites, {len(accum)} paires stop_id_a/stop_id_b")

    print(f"Ecriture de {OUT_FILE}...")
    with open(OUT_FILE, "w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(["stop_id_a", "nom_a", "stop_id_b", "nom_b", "distance_metres", "nb_observations"])
        for (a_id, b_id), (somme, nb) in sorted(accum.items()):
            writer.writerow([
                a_id,
                stop_names.get(a_id, "?"),
                b_id,
                stop_names.get(b_id, "?"),
                round(somme / nb),
                nb,
            ])

    print("Termine.")


if __name__ == "__main__":
    sys.exit(main())
