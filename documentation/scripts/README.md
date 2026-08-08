# Scripts d'extraction et de correction

Scripts ponctuels (Python/SQL) écrits pendant les sessions de peuplement de données
(3bis/7bis, corrections réseau métro, RER, réseau complet IDFM). Conservés ici plutôt
que dans un dossier temporaire pour pouvoir être rejoués ou adaptés plus tard.

- `extraire_*.py` : extraction de topologie/durées/distances/stations depuis le GTFS complet
  IDFM (`documentation/IDFM-gtfs/`, non versionné, ~1.3 Go) ou les CSV du portail
  data.iledefrance-mobilites.fr.
- `comparer_stations.py` : rapprochement de noms de stations (normalisation) entre deux listes.
- `audit_ligneN.sql` : requêtes de vérification de la topologie/correspondances d'une ligne de
  métro par rapport au plan officiel RATP.
- `check_*.sql`, `investigate_*.sql`, `longest_troncons.sql`, `verif_*.sql` : requêtes
  d'investigation ponctuelles.
- `fix_*.sql`, `peupler_*.sql` : corrections/peuplements appliqués en base (dev puis prod).
- `donnees-extraites/` : sorties CSV des scripts d'extraction (durées/distances réelles par
  tronçon, stations RER) — évite de re-parcourir le GTFS complet (plusieurs minutes) si besoin
  de rejouer un import.
