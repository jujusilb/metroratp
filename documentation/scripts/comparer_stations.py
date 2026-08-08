#!/usr/bin/env python3
import csv
import unicodedata
import re
from pathlib import Path

SCRATCH = Path(r"C:\Users\Jujusilb\AppData\Local\Temp\claude\C--Users-Jujusilb-Documents-BADE-DE-DONNEE-PARIS-metro-ratp-symfony-site-metroratp\3a92a524-ed2c-4362-bda2-4f0b9916cc58\scratchpad")

def normaliser(label):
    label = label.replace("—", "-").replace("–", "-")
    label = unicodedata.normalize("NFD", label)
    label = "".join(c for c in label if unicodedata.category(c) != "Mn")
    label = label.lower()
    label = re.sub(r"[^a-z0-9]+", " ", label)
    return label.strip()

existantes = {}
with open(SCRATCH / "stations_existantes.txt", encoding="utf-8") as f:
    next(f)
    for line in f:
        label = line.strip()
        if label:
            existantes[normaliser(label)] = label

rer_par_ligne = {}
with open(SCRATCH / "stations_rer.csv", encoding="utf-8") as f:
    reader = csv.DictReader(f)
    for row in reader:
        rer_par_ligne.setdefault(row["ligne"], []).append(row["station"])

matches = []
no_matches = []
for ligne, stations in rer_par_ligne.items():
    for s in stations:
        n = normaliser(s)
        if n in existantes:
            matches.append((ligne, s, existantes[n]))
        else:
            no_matches.append((ligne, s))

print(f"=== MATCHES EXACTS ({len(matches)}) ===")
for ligne, s, existante in matches:
    print(f"  {ligne} | {s} -> {existante}")

print(f"\n=== SANS MATCH ({len(no_matches)}) ===")
for ligne, s in no_matches:
    print(f"  {ligne} | {s}")
