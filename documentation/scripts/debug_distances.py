import csv
import math
import sys

sys.stdout.reconfigure(encoding='utf-8')

REFERENTIEL_DIR = 'C:/Users/Jujusilb/Documents/BADE DE DONNEE/PARIS/idfmobilite/csv/'
coords = {}
noms = {}
with open(REFERENTIEL_DIR + 'zones-de-correspondance.csv', encoding='utf-8-sig') as f:
    reader = csv.DictReader(f, delimiter=';')
    for row in reader:
        try:
            coords[row['ZdCId']] = (float(row['ZdCXEpsg2154']), float(row['ZdCYEpsg2154']))
            noms[row['ZdCId']] = row['ZdCName']
        except (ValueError, KeyError):
            pass


def dist(a, b):
    (xa, ya), (xb, yb) = coords[a], coords[b]
    return math.hypot(xb - xa, yb - ya)


chaine = ['71410', '72211', '72598', '72641', '72652', '72648', '72646']
total = 0
for i in range(len(chaine) - 1):
    d = dist(chaine[i], chaine[i + 1])
    total += d
    print(f"{noms[chaine[i]]} -> {noms[chaine[i+1]]}: {d:.0f} m")
print(f"Somme des sauts: {total:.0f} m")
print(f"Distance directe Gare du Nord -> Aulnay-sous-Bois: {dist('71410','72646'):.0f} m")
