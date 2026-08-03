#!/usr/bin/env python3
"""
Cree et peuple la base "metroratp" en une seule commande, a partir du dump SQL complet
(structure + donnees) genere par mysqldump : metroratp-complet.sql, dans le meme dossier.

Le dump contient deja "DROP DATABASE IF EXISTS metroratp" puis "CREATE DATABASE metroratp" :
inutile de creer la base au prealable, ce script se connecte au serveur SANS preciser de base
et laisse le dump s'en charger.

Prerequis :
  - Un serveur MySQL/MariaDB accessible (le meme moteur que celui du dump : MariaDB 10.4).
  - Le client en ligne de commande "mysql" installe et accessible (dans le PATH, ou son
    chemin precise via --mysql-bin). Ce script delegue l'import a ce client plutot que de
    reimplementer un parseur SQL en Python : un dump mysqldump utilise des directives
    specifiques (/*!40101 ... */, DELIMITER, etc.) que seul le client officiel interprete
    de facon fiable.

Usage (Windows, lanceur "py") :
    py importer_base_complete.py
    py importer_base_complete.py --host 127.0.0.1 --port 3306 --user root --password ""
    py importer_base_complete.py --mysql-bin "C:\\xampp\\mysql\\bin\\mysql.exe"

Usage (Linux/Mac) :
    python3 importer_base_complete.py
"""

import argparse
import subprocess
import sys
from pathlib import Path

DUMP_FILE = Path(__file__).parent / "metroratp-complet.sql"


def main():
    parser = argparse.ArgumentParser(description="Importe le dump SQL complet de la base metroratp (structure + donnees)")
    parser.add_argument("--host", default="127.0.0.1", help="Hote du serveur MySQL/MariaDB (defaut : 127.0.0.1)")
    parser.add_argument("--port", default="3306", help="Port du serveur (defaut : 3306)")
    parser.add_argument("--user", default="root", help="Utilisateur MySQL/MariaDB (defaut : root)")
    parser.add_argument("--password", default="", help="Mot de passe (defaut : vide, comme en environnement de dev local)")
    parser.add_argument("--mysql-bin", default="mysql", help="Chemin vers l'executable mysql si absent du PATH (defaut : 'mysql')")
    args = parser.parse_args()

    if not DUMP_FILE.exists():
        print(f"Fichier introuvable : {DUMP_FILE}", file=sys.stderr)
        sys.exit(1)

    commande = [args.mysql_bin, "-h", args.host, "-P", str(args.port), "-u", args.user]
    if args.password:
        commande.append(f"-p{args.password}")

    taille_ko = DUMP_FILE.stat().st_size // 1024
    print(f"Import de {DUMP_FILE.name} ({taille_ko} Ko) sur {args.host}:{args.port}...")

    with open(DUMP_FILE, "rb") as f:
        resultat = subprocess.run(commande, stdin=f)

    if resultat.returncode != 0:
        print("Echec de l'import. Verifiez que le serveur est demarre et les identifiants corrects.", file=sys.stderr)
        sys.exit(resultat.returncode)

    print("Base 'metroratp' creee et peuplee avec succes (structure + donnees).")


if __name__ == "__main__":
    main()
