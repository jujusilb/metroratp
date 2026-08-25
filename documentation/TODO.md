# À faire / pistes en attente

Historique complet (tâches achevées, avec leur contexte technique détaillé) migré vers `/tache`
(base de données, réservé `ROLE_ADMIN`) le 2026-08-16 — voir `documentation/commande.md` pour le
détail de cette migration. Ce fichier ne garde désormais que ce qui reste réellement à faire.

## Automatisation (conduite automatique + portes palières) — fait (2026-08-25)

Idée utilisateur initiale : table `Automatisation` (id, label : "porte de rame"/"porte
palière"/"total") + table de liaison Ligne. **Repensé en cours de route** suite à une remarque
utilisateur ("il en manque non ? sur la 13 il y a plein de palière il me semble") : la conduite
automatique ("total") est bien une propriété de toute la ligne (matériel roulant + signalisation,
tout ou rien), mais les portes palières s'installent quai par quai et un déploiement peut rester
partiel pendant des années (confirmé concrètement sur la Ligne 13, cf. plus bas) — une seule table
liée à `Ligne` ne pouvait pas représenter ça sans mentir sur les stations non équipées.

**Design final** (2 champs simples plutôt que 2 tables, sur suggestion utilisateur) :
- **`Ligne.dateAutomatisationTotale`** (nullable) : date à laquelle la ligne est devenue
  entièrement automatique (sans conducteur), réseau entier. Remplace la table `Automatisation`
  d'origine (supprimée, migration `Version20260825221456`).
- **`Desserte.datePortePaliere`** (nullable) : date d'installation des portes palières sur ce quai
  précis, pour cette ligne précise — même principe que `Desserte.styleStation` (champ nullable,
  jamais renseigné pour le bus, `estAccessible`, `climatisation`...), pas de nouvelle table de
  liaison, `Desserte` est déjà la jonction Station×Ligne.
- "Porte de rame" abandonné : jamais trouvé de date distincte documentée pour cette étape dans les
  recherches ci-dessous (probablement simultanée à la livraison du matériel roulant, pas un
  événement propre).

Recherche menée via les articles Wikipédia dédiés ("Automatisation de la ligne 1/4/13 du métro de
Paris", wikitext brut, tableaux "Installation des portes palières par station" quand disponibles) :

- **Ligne 1** : `dateAutomatisationTotale` = **22/12/2012** ("exploitation pendant toute la semaine
  avec des navettes automatiques"). `datePortePaliere` peuplé pour ses **25 Desserte**, une date
  précise par station (tableau complet trouvé) : de Bérault (mars 2009, première équipée) à
  Bastille/Nation/Charles de Gaulle-Étoile (avril 2011, dernières).
- **Ligne 4** : `dateAutomatisationTotale` = **15/12/2023** ("retrait des dernières rames à
  conduite manuelle"). `datePortePaliere` peuplé pour ses **29 Desserte** : 27 stations historiques
  avec un tableau complet (Mouton-Duvernet juin 2018, première, à Porte d'Orléans février 2021,
  dernière — dates en granularité mois, 1er jour du mois retenu par convention quand la source
  donne une plage, ex. "Août - octobre 2020" → 2020-08-01) + Barbara et Bagneux - Lucie Aubrac
  (13/01/2022, stations neuves de l'extension sud, équipées dès l'ouverture).
- **Ligne 14** : `dateAutomatisationTotale` et `datePortePaliere` (ses **21 Desserte**) = **toutes
  15/10/1998** (première ligne entièrement automatique dès l'origine, portes palières d'origine).
  Note : l'article Wikipédia se contredit sur le jour exact (14 ou 15) ; 15 retenu (infobox +
  sources externes, dont l'archive INA).
- **Ligne 13 — exactement le cas qui a motivé la refonte** : `dateAutomatisationTotale` **non
  renseignée** (automatisation seulement votée le 7 décembre 2022, attribuée à Siemens Mobility en
  août 2025, calendrier prévisionnel 2027 puis 2032-2035 selon les sources — un projet, pas un
  fait accompli). En revanche `datePortePaliere` **peuplé pour 13 de ses 32 Desserte** (dispositif
  antérieur et sans rapport avec ce projet d'automatisation, posé entre 2008 et 2012, granularité
  année seule → 1er janvier retenu par convention) : Châtillon-Montrouge (2008), Miromesnil (2010),
  Invalides/Champs-Élysées-Clemenceau/Saint-Lazare/Liège/Saint-Denis-Porte de Paris/Basilique de
  Saint-Denis (2011), Place de Clichy/Varenne/Saint-François-Xavier/Duroc/Montparnasse-Bienvenüe
  (2012). Les 19 autres Desserte restent `NULL` — exactement la réalité du terrain, chose que
  l'ancien schéma (1 date par Ligne) ne pouvait pas représenter.
- Autres lignes de métro/RER : pas de projet d'automatisation identifié à ce jour, rien à créer.

Affiché : badge "Automatique depuis [date]" sur `/ligne/{id}` si `dateAutomatisationTotale` est
renseignée ; colonne "Porte palière" (mois/année) dans le tableau des Dessertes de `/station/{id}`.
Volontairement pas d'ajout aux formulaires d'édition manuelle (`LigneType`/`DesserteType`) — même
convention que `climatisation`/`estAccessible`/`equipementArret`, déjà exclus de ces formulaires :
ce sont des données de recherche/import, pas des champs à saisir à la main station par station.

Vérifié : `php bin/phpunit` (137), `npx jest` (51), navigateur (badge Ligne 1, colonne porte
palière sur la fiche Bérault).

## Style physique des Accès — Guimard fait (2026-08-17), escalator/ascenseur fait (2026-08-20), mât toujours sans source

Voir `documentation/commande.md` pour le détail. `StyleAcces` créée (même schéma que
`StyleStation`) avec CRUD complet, `Acces.styleAcces` ajouté.

**Édicule Guimard** : le constat Wikidata initial était juste (confirmé : un seul accès individuel
avec `P84`=Guimard + `P31`=entrée), mais une bien meilleure source existe — un annuaire
patrimonial listant les 88 édicules Guimard classés/inscrits monuments historiques à Paris
(6 stations à édicule complet protégées dès 1965 : Cité, Porte Dauphine, Abbesses, Pigalle,
Ternes, Tuileries ; le reste par décret collectif de 1978). **22 Acces tagués avec certitude**
(sur 64 stations candidates) : le reste a soit plusieurs Acces sans détail suffisant dans la
source pour savoir lequel est le vrai édicule (ex. Châtelet 11 accès, Bastille 9, République 12 —
y compris des stations emblématiques comme Porte Dauphine ou Abbesses côté "plusieurs accès"),
soit carrément aucun Acces enregistré (lacune de données préexistante sur Saint-Lazare,
Saint-Michel, Quatre-Septembre, Colonel Fabien, Villiers, Chardon-Lagache, Louvre—Rivoli,
Palais Royal—Musée du Louvre, Réaumur—Sébastopol, Barbès—Rochechouart — 10 stations). Découverte
au passage : la station de métro "Wagram" (ligne 3, existe réellement) est totalement absente de
la base, ni comme Station complète ni comme Desserte — vraie lacune, pas liée à cette tâche.
**Fait (2026-08-24)** : le constat était partiellement faux — la Station "Wagram" (code_externe
`71423`, Paris 17e) existait bien, avec 2 Desserte bus (lignes 31/93), mais aucune Desserte pour
la Ligne 3 : un Troncon reliait directement Malesherbes↔Pereire chez nous (court-circuit sautant
Wagram), alors que le GTFS actuel confirme Wagram entre les deux (position 19/24 dans l'ordre réel
des arrêts). Cause probable : un second Station homonyme existe (Maisons-Laffitte, code_externe
différent) — le rattachement par simple label a sans doute été évité par prudence lors d'un import
passé, sans jamais être repris pour cette Station précise (identifiable sans ambiguïté par son
`code_externe`). `app:corriger-troncon-wagram` (nouvelle commande, idempotente) : Desserte créée,
Troncon direct remplacé par Malesherbes↔Wagram↔Pereire (2 Troncon), les 2 `Mission` existantes sur
l'ancien Troncon repointées vers les nouveaux `TronconDesserte` correspondants + 2 `Mission`
supplémentaires créées pour Wagram (sans quoi la suppression de l'ancien Troncon échouait sur une
contrainte de clé étrangère `Mission→TronconDesserte`). Vérifié en navigateur : le trajet
Villiers→Pereire passe désormais par "Villiers → Malesherbes → Wagram → Pereire" (3 étapes, avant
2), avec un conseil de position affiché.

**Escalator/ascenseur : fait (2026-08-20)**, en reprenant l'investigation ci-dessus qui avait
conclu à tort à une impasse. Le vrai problème n'était pas l'absence de données OSM, mais le tag
interrogé : `escalator` (générique, quasi inexistant) au lieu du tagage standard
`highway=steps`+`conveying=*` (escalier mécanique) et `highway=elevator` (ascenseur). Réinterrogé
via Overpass API (même miroir `lz4.overpass-api.de`) : 1512 escaliers mécaniques + 1427 ascenseurs
sur toute l'Île-de-France. Rattaché par proximité géographique à `Acces` (aucun identifiant officiel
ne relie OSM à nos données), avec un seuil de confiance strict (30m + deuxième candidat nettement
plus loin, même discipline que le tagging Guimard) : 227 Acces avec escalier mécanique, 209 avec
ascenseur (`Acces.aEscalierMecanique`/`aAscenseur`, voir `app:importer-escaliers-ascenseurs-osm`).
Voir `documentation/commande.md` pour le détail.

**Mât** : toujours aucune piste concrète (ce serait une valeur par défaut déduite, pas une donnée
sourcée) — contrairement à escalator/ascenseur, aucun tag OSM standard équivalent identifié.

## Pistes de données IDFM non encore exploitées

* `emplacement-des-gares-idf-data-generalisee.csv` — vérifié (2026-08-17), **pas exploitable pour
  combler des trous** : sur les 999 gares du fichier, 996 correspondent déjà à une Station chez
  nous par `code_externe` (coordonnées cohérentes avec les nôtres, écart de quelques dizaines de
  mètres — source fiable mais redondante). Les 11 Station sans coordonnées ne sont tout simplement
  pas dans ce fichier (11/11 absentes, aucune n'a pu être comblée). Pas de colonne ville/commune
  dans ce fichier (donc inutile aussi pour les 571 Station sans `ville`). `exploitant` fait doublon
  avec `Ligne.gestionnaire`, déjà modélisé. Les 3 gares du fichier sans correspondance chez nous
  (Traité de Rome, Noveos, Louise Michel) sont des noms trop génériques pour matcher sans risque
  (des dizaines d'homonymes dans des communes sans rapport, même phénomène que
  "République"/"Gambetta" déjà rencontré). Rien à en tirer avec les données actuelles.
* `transfers.txt` : déjà exploité (voir `documentation/commande.md`, 2026-08-17) — 205/505
  correspondances même-station à distance NULL affinées (9 via `code_externe` direct, 196 via un
  repli par nom sur jumeau non ambigu). Reste 300 correspondances : soit label ambigu (167,
  volontairement laissé NULL — ex. "République"/"Gambetta" existent dans des dizaines de communes
  sans rapport), soit aucun jumeau exploitable (142). Vrai déblocage = fusion des Stations
  dupliquées (voir plus bas), pas une piste transfers.txt supplémentaire.
* `sdap-arrets-associes.csv` (36696 lignes, un ArR par ligne) : accessibilité détaillée par
  arrêt — `ArRAccessibility`/`ArRAudibleSignals`/`ArRVisualSigns` (signalétique sonore/visuelle
  PMR) + `Extensions` (JSON imbriqué, ex. climatisation) + `bookingRules`. Bien plus fin que
  `Station.accessibilitePmr` actuel ; a du sens surtout une fois un niveau Arrêt/ArT modélisé
  (voir plus bas), pour rattacher l'accessibilité au bon quai plutôt qu'à toute la station.

## Écarts arrêts référentiel/OpenStreetMap — fait (2026-08-19)

`ecarts-arrets-referentiel-et-openstreetmap.csv` : entité `EquipementArret` (mobilier physique OSM
— banc, abri, poubelle, éclairage, bande tactile — rattachée à `Station` via `relations.csv`, ArTId
→ ZdCId → `codeExterne`). 40511 `EquipementArret` importés, couvrant 12867 Station.

## Arrêt Transporteur (ArT) — fait, modèle revu le 2026-08-20

**Décision de modélisation** (discussion avec l'utilisateur, 2026-08-20) : pas de table
`ArretTransporteur` séparée — elle dupliquait l'identité de Station (nom/coordonnées) sans lui
apporter de granularité réelle. Le principe retenu : une Station est unique par nom+position, une
Desserte = Station+Ligne ; chaque donnée ArT est rangée selon si elle dépend ou non de la ligne :

* **`Station.zoneTarifaire`** (`arrets-transporteur.csv`, ArTFareZone) — propriété du lieu, pas de
  la ligne. `app:importer-zone-tarifaire`. **Piège découvert** : un ZdCId peut regrouper des ArT de
  villes différentes (collision de nom au niveau du référentiel source — ex. "Les Sablons" existe à
  la fois comme station de métro à Neuilly ET comme arrêt de bus à Ecquevilly, à 30km). Deux
  vérifications ajoutées (cohérence des villes ; distance ArT↔Station quand connue) : 12768 Station
  fiables sur 13643 candidates. Résidu documenté dans le code : quand la Station elle-même n'a pas
  de coordonnées (phénomène "Stations dupliquées" ci-dessous), aucune vérification n'est possible.
* **`Desserte.estAccessible`/`signalisationSonore`/`signalisationVisuelle`** — dépend du matériel
  roulant de CETTE ligne précise (un bus à plancher bas sur une ligne peut être accessible pendant
  qu'une autre ligne au même arrêt physique ne l'est pas). Source : `sdap-arrets-associes.csv`
  (route_id/stop_id, un lien 100% officiel vers Ligne ET Station — bien plus précis que
  `arrets-transporteur.csv` seul, qui n'a aucune notion de ligne). `app:importer-accessibilite-dessertes`,
  35005 Desserte mises à jour.
* **`Desserte.equipementArret`** (FK vers `EquipementArret`, conservée) — le mobilier physique est
  RÉFÉRENCÉ plutôt que dupliqué : plusieurs Desserte d'une même Station (une par ligne) qui
  partagent le même arrêt physique (cas fréquent en bus, un seul poteau/banc pour plusieurs lignes)
  pointent vers le MÊME EquipementArret. `app:importer-equipements-arrets` (étendue), 29977 Desserte
  reliées.

Voir `documentation/commande.md` (session du 2026-08-20) pour le détail complet de la discussion et
des vérifications.

## Lignes à embranchements complexes

**RER C : fait (2026-08-17)**, voir `documentation/commande.md` pour le détail. Le mécanisme
`Direction`/tronçon (un par terminus réel, parcours récursif de l'arbre) a tenu tel quel pour un
arbre à plusieurs niveaux — 75 stations, 74 tronçons (arbre pur, aucun maillage contrairement au
RER D), 6 vrais terminus, 4 embranchements (Brétigny, Viroflay Rive Gauche, Choisy-le-Roi, Champ
de Mars Tour Eiffel). La vraie difficulté n'était pas le modèle de données mais la
**reconnaissance** : la réduction géométrique automatique (retire les raccourcis de missions
semi-directes) laissait 10 fausses arêtes en trop sur le corridor Paris-Choisy-le-Roi à cause de
plusieurs niveaux de missions qui se chevauchent — corrigé en changeant l'algorithme de "plus long
d'abord" à "plus court d'abord contre un graphe déjà confirmé", plus robuste contre ce cas.

**RER D, zone Évry/Corbeil/Juvisy (découvert le 2026-08-09) — troncons faits (2026-08-21)** : pas
un simple aller-retour mais un vrai maillage local avec au moins 2 cycles indépendants
(Villeneuve-Saint-Georges ↔ Corbeil-Essonnes via Juvisy *ou* via Melun ; Corbeil-Essonnes ↔
Viry-Châtillon via Évry-Val-de-Seine *ou* via Grigny-Centre). Même limite du modèle
Direction/tronçon (pense un arbre, pas un graphe avec cycles) que pour la RER C. Le reste de la
ligne D (tronc Creil ↔ Villeneuve-Saint-Georges, branche Malesherbes) est un arbre normal et a été
construit. Le maillage lui-même reste sans `Direction`/`Mission` (le modèle ne peut toujours pas
les représenter), mais ses `Troncon`/`TronconDesserte` sont désormais construits
(`app:construire-maillage-rer-d`, voir section "Résiduel..." ci-dessous) : suffisant pour le
calculateur de trajet, qui ne lit pas Direction/Mission.

## Stations Metro/Tramway/RER dupliquées (découvert le 2026-08-09)

En creusant pourquoi les correspondances inter-modes rataient les grosses gares (Gare du Nord,
La Défense...), découverte d'un problème bien plus large : **~486 stations Métro/Tramway/RER
"originales"** (créées à la main tôt dans le projet, avant `app:importer-reseau-complet`)
**ont un doublon exact** créé plus tard par l'import du réseau complet — même lieu réel, même
ZdCId officiel une fois résolu, mais deux lignes `Station` distinctes (l'originale avec
`code_externe` NULL, la nouvelle avec le bon ZdCId). Quasiment tout le réseau métro/RER/tram
d'origine est concerné, pas seulement les gros hubs.

Contournement en place : `ConstruireCorrespondancesInterModesCommand` regroupe par **label** de
station plutôt que par id, donc les correspondances entre modes LOURDS (métro/RER/tram) fonctionnent
malgré les doublons. Mais le problème de fond reste : toute future fonctionnalité qui regroupe par
`Station` (pas par label) tombera dans le même piège.

**Impact concret trouvé le 2026-08-17** : ce contournement ne couvrait jamais le bus, ce qui
cassait silencieusement tout trajet censé changer de mode bus↔métro/RER/tram — signalé par
l'utilisateur (`/trajet` ne proposait jamais de sortir du bus). Cause : la Station "originale"
porte la vraie Desserte métro/RER/tram (et toute sa topologie de Troncon), la Station "doublon
GTFS" porte toutes les Correspondance construites depuis transfers.txt (bus compris, puisque leur
construction a besoin d'un `code_externe`) — les deux ne se rencontrent jamais dans le graphe.
Nouveau contournement complémentaire : `app:construire-correspondances-stations-dupliquees` relie
chaque Desserte de la Station originale à chaque Desserte de sa jumelle GTFS quand le label
correspond à exactement une seule jumelle (358/512 stations concernées, 3071 correspondances
créées ; 74 labels ambigus et 80 sans jumelle laissés de côté, même discipline que partout ailleurs
cette session). Restaure le changement de mode bus↔lourd sans fusionner les Station. Vérifié en
conditions réelles : Kremlin-Bicêtre (bus 131) → métro 7 → métro 14 → RER A → RER E → bus 207.

**Vraie correction — fait pour 371/534 paires (2026-08-21/22)** : `app:fusionner-stations-dupliquees`
(nouvelle commande) fusionne les paires **non ambiguës** (même label exact ET coordonnées à moins
de 300m — vérifié sans aucun cas ambigu ni aucun conflit de valeur sur ces 371 paires) : la
Station "originale" (réellement visitée) devient canonique, récupère les colonnes qui lui
manquaient (`code_externe`, `ville`, `zone_tarifaire`, `plan_id`...), les 9 tables qui référencent
`Station` par FK directe (`desserte`, `sortie`, `equipement_arret`, `position_rame`,
`defibrillateur`, `fontaine_eau`, `point_de_vente`, `sanisette_publique`, `sanitaire`) sont
repointées vers elle, puis la jumelle ZdC est supprimée. Vérifié : aucune Desserte sur la même
Ligne des deux côtés (donc aucun doublon (station,ligne) après fusion, `Correspondance`/`Direction`/
`TronconDesserte` n'ont pas besoin d'être touchées, elles référencent `Desserte` dont l'id ne
change pas). Résultat concret sur "Nation"/"La Défense" : Sorties, Points de vente, Sanitaires,
Défibrillateurs — jusque-là invisibles sur la page réellement consultée — apparaissent enfin.
Sauvegarde complète (`mysqldump`) des tables concernées prise avant exécution, en local et en
prod. **163 paires volontairement non fusionnées** dans un premier temps (le simple rapprochement
par label seul est dangereux : 83 stations "originale" ont plusieurs homonymes par label — ex:
"Victor Hugo", 35 candidats, un nom de rue commun sans rapport avec la même station physique) : 82
sans aucun nom correspondant, 81 sans coordonnées pour vérifier. Repérage initial via
`documentation/scripts/backfill_code_externe_stations_originales.py` (2026-08-09/20).

**Complément (2026-08-22)** : sur une suggestion utilisateur d'importer
`emplacement-des-gares-idf-data-generalisee.csv` (référentiel officiel IDFM des gares
train/RER/métro/tramway, commité dans `documentation/scripts/donnees-extraites/`), ajout d'une
3e passe à `app:importer-coordonnees-geographiques` (repli par `nom_ZdC` quand aucune jumelle
n'est déjà positionnée dans notre propre base) : **81 Stations supplémentaires positionnées**
(2 ambiguës exclues : "Saint-Fargeau", "Pont de Rungis Aéroport d'Orly" — homonymes réels). Ces
nouvelles coordonnées ont mécaniquement débloqué **63 fusions de Station dupliquées
supplémentaires** en relançant `app:fusionner-stations-dupliquees` (total cumulé : 434/534).
"Châtelet" (originale, id 15) reste un cas exclu car génuinement ambigu (2 homonymes réels : Paris
et Montereau-Fault-Yonne), pas un manque de coordonnées — à revoir manuellement si besoin.

## Autres pistes notées en cours de route

- `Station.codeExterne` périmé (repéré le 2026-08-22 via un signalement utilisateur sur "Hôtel de
  Ville", 0 Sortie/accessibilité/coordonnées affichées) — **fait** : même symptôme que
  `Ligne.codeExterne` incohérent (2026-08-17), sur `Station` cette fois. 14 Station (des noms très
  courants : Concorde, Villiers, Hôtel de Ville, Saint-Augustin, Rue du Bac...) avaient un
  `code_externe` absent du GTFS actuel — invisibles à `app:fusionner-stations-dupliquees` (qui ne
  cherche que `code_externe IS NULL`), donc jamais fusionnées avec leur vraie jumelle ZdC-liée qui
  porte les vraies données. Nouvelle commande `app:corriger-code-externe-perime` : désambiguïsation
  par proximité au voisin `Troncon` déjà positionné (ces 14 Station n'ont elles-mêmes aucune
  coordonnée pour se départager directement entre homonymes) — marge nette à chaque fois (moins de
  700m pour 13/14, contre 3 à 27 km pour le 2e candidat le plus proche). 14/14 corrigées.
- "Conseils de position dans la rame" (`PositionRame`) — **déplacé (2026-08-22)**, sur demande
  utilisateur : n'a de sens qu'en connaissant la destination réelle ("pour rejoindre X, se placer
  Y"), retiré de la fiche Station (où il s'affichait hors de tout contexte) et affiché à la place
  dans le calculateur de trajet (`/trajet`, vue "Détaillé"), à la fin de chaque tronçon emprunté —
  c'est justement là qu'on sait déjà s'il faut changer de ligne ou si c'est l'arrivée.
  `PositionRameRepository::trouverParStationEtLigne()` (nouvelle), affichage filtré par Ligne
  precise. Note du 2026-08-22, **très sous-estimée** : "le dataset source contient parfois
  plusieurs lignes identiques pour une même destination" — en réalité, **bien plus grave**,
  signalé concrètement le 2026-08-23 par l'utilisateur sur un trajet réel (Villejuif Léo Lagrange
  → Opéra/Auber via RER A, correspondance ligne 14→7) : le tronçon ligne 14 affichait **des dizaines
  de conseils "Pour rejoindre..."** au lieu d'un seul. Vérifié précisément : `station_id` de "Maison
  Blanche" seule a **113 lignes `PositionRame`** toutes lignes confondues, dont **35 quasi-identiques**
  pour le seul couple (Ligne 14, destination "av. d'Italie") — ne différant que par des détails
  d'équipement/position triviaux (Escalier/Escalator/Ascenseur, Avant/Milieu/Arrière à des index
  différents). Cause racine à deux niveaux, tous deux dans
  `PositionRameRepository::trouverParStationEtLigne()` :
  1. **Aucun filtre sur la direction réelle** : la méthode retourne TOUTES les `PositionRame` du
     couple (Station, Ligne), quelle que soit leur `destination` — hors le tronçon affiché n'a
     qu'UNE seule direction de circulation pertinente (celle vers laquelle le trajet calculé
     continue réellement). Résultat : plusieurs destinations sans rapport avec le trajet en cours
     s'affichent toutes en vrac (ex. "av. d'Italie", "Maison Blanche", "r. Bourgon", "r. de la
     Vistule", "r. du Tage", "r. Tagore" toutes mélangées pour la même Station+Ligne).
  2. **Aucune déduplication** : même en se limitant à LA bonne destination, le dataset source
     contient des dizaines d'entrées quasi-identiques (cf. les 35 pour "av. d'Italie") jamais
     réduites à une seule recommandation.
  Reformulation du besoin réel par l'utilisateur (ex. concret) : "je monte à Villejuif Léo Lagrange,
  je sais que j'ai une correspondance pour le RER A à Opéra/Auber, je sais qu'à Villejuif je monte à
  l'avant parce que c'est à Opéra que je descends et qu'à Opéra c'est à l'avant — pourquoi me le
  dire à chaque station pour chaque direction ?" — la bonne info est **une seule ligne par
  correspondance/segment embarqué**.

  **Fait (2026-08-23)**. Piste initiale (filtrer par la Station de sortie réelle du tronçon)
  abandonnée après un vrai retour utilisateur qui a change la comprehension du probleme : les 2
  valeurs "contradictoires" (Avant/Arrière) observées pour une même destination n'étaient pas du
  bruit mais **le sens de circulation** — un train a 2 sens, la position utile s'inverse selon le
  sens (exemple donné : la salle des pas perdus de Gare de l'Est est à l'arrière du train si on en
  part, à l'avant si on y arrive). Vérifié dans le GTFS complet (`documentation/IDFM-gtfs/csv/` —
  déjà présent en local, pas de téléchargement nécessaire malgré une estimation initiale erronée de
  ~1,3 Go) : un `stop_point` (quai précis, `from_id` du CSV source) correspond bien à un seul sens
  (`direction_id`/`trip_headsign` GTFS constants pour tous les trips qui le desservent).

  Solution : `documentation/scripts/extraire_conseils_position.php` réécrit pour résoudre, par
  `from_id`, un trip représentatif (via `stop_times.txt`, 2 passages sur le fichier ~11,8M lignes)
  puis son `direction_id`/`trip_headsign` (`trips.txt`) et surtout la ZdC du **prochain arrêt réel
  dans ce sens précis** (`zdcSuivant`) — c'est ce dernier qui permet de départager le bon sens sans
  reconstruire l'ordre global de la Ligne (fragile sur les lignes en maillage). `PositionRame` gagne
  `directionId`/`terminusReel`/`prochaineStation` (`ManyToOne` vers `Station`, migration
  `Version20260823140000.php`). `PositionRameRepository::trouverPourEmbarquement(station, ligne,
  prochaineStation)` (remplace `trouverParStationEtLigne()`) : filtre directement par la Station
  suivante réellement empruntée, retourne au plus 1 résultat (ou `null` si le sens ne peut pas être
  confirmé — rien afficher plutôt qu'un conseil pour le mauvais sens).
  `TrajetController::construireSegmentsPourAffichage()` : le conseil est désormais rattaché à la
  Station de **départ** (embarquement) du tronçon, pas à son arrivée — actionnable avant de monter,
  et un seul conseil pour tout le tronçon (le sens ne change pas en cours de route), déterminé par
  la toute première Station suivante du tronçon calculé (`dessertes[1]`).
  Vérifié en local (compte de test, trajet réel Villejuif Léo Lagrange → Les Mousquetaires) : le
  tronçon Ligne 14 (5 stations, montrait plus de 100 lignes de conseils avant le fix) n'affiche
  plus qu'une seule ligne "🚃 Montez Milieu (5/8) — Escalator". `php bin/phpunit` (137), `npx jest`
  (51) : tout passe.
- Page `/ligne/{id}` (signalé le 2026-08-22) — **les deux soucis sont fait (2026-08-25), voir la
  section dédiée plus bas ("Page /ligne/{id} — pastilles collées...") pour le détail complet de la
  résolution.** Description initiale du problème conservée ci-dessous pour l'historique :
  (`templates/ligne/show.html.twig`, macro `renderSegment`, alimentée par `Ligne::getParcoursSegments()`) :
  1. **Affichage** : les pastilles de correspondance et le nom de la station sont sur la même
     ligne (`d-flex align-items-center gap-2`), ex. "1, 2, 3, 4, 12, 23, 42 Hôtel de Ville" — à
     séparer visuellement (correspondances au-dessus, nom de la station en dessous, ou toute autre
     mise en page plus lisible qu'une seule ligne dense quand une station a beaucoup de
     correspondances).
  2. **Ordre des stations pas toujours le cheminement réel** : `getParcoursSegments()` part d'un
     terminus (Desserte de degré 1) et suit `Troncon` en profondeur, gérant les embranchements
     (arbre) mais pas les vrais graphes à cycles (maillage). Vérifié concrètement sur `/ligne/22`
     (RER D) : après Juvisy, l'algorithme rentre dans la branche Malesherbes (Boigneville,
     Maisse...) avant de revenir sur la boucle Évry/Corbeil/Grigny puis de rejoindre "Viry-Châtillon"
     une 2e fois ("rejoint la ligne principale") — l'ordre affiché ne correspond pas au vrai
     cheminement physique dans cette zone. Cause directe : le maillage RER D (voir plus haut,
     "Lignes à embranchements complexes") a été construit cette session avec `Troncon`/
     `TronconDesserte` seuls, sans `Direction`/`Mission` (`ConstruireMaillageRerDCommand`) — or
     `getParcoursSegments()` ne s'appuie que sur `Troncon` (ni Direction ni Mission), donc il
     hérite du problème dès qu'un graphe n'est plus un arbre. Plusieurs lignes Transilien
     construites la même session (H, J, L, N, R — voir plus haut) ont le même type d'excédent
     d'arêtes par rapport à un arbre pur et sont probablement affectées aussi, à vérifier au cas
     par cas. Pas de correctif simple identifié : nécessiterait soit de détecter/couper les cycles
     pour cet affichage spécifique (au risque de perdre des arêtes réelles), soit un vrai ordre de
     référence par ligne (ex: à partir des horaires GTFS complets, hors périmètre de ce qui est
     commité dans le dépôt).
- Fiche Gestionnaire (signalé le 2026-08-22) — **note initiale erronée, corrigée le 2026-08-23** :
  en relisant `templates/gestionnaire/show.html.twig` avant de construire la fiche Ville (besoin du
  même patron), la liste des `Ligne` gérées ("Lignes gérées", `ul.list-group`) **existait déjà** au
  moment du signalement — mauvaise vérification initiale (conclusion tirée de l'entité seule,
  sans relire le template). Rien à faire ici.
- Ligne 3139 introuvable (signalé le 2026-08-22 ; réseau "Pays Briard", exploitant Keolis Portes et
  Val de Brie, AO Île-de-France Mobilités). Vérifié : la ligne existe bien dans
  `documentation/IDFM-gtfs/csv/referentiel-des-lignes.csv` (ID_Line `C01058`, statut `active`), mais
  est absente de `documentation/scripts/donnees-extraites/reseau_complet.csv` — la vraie source de
  `app:importer-reseau-complet`, générée par `extraire_reseau_complet.py`, qui ne retient que les
  route_id effectivement vus dans `trips.txt` du flux GTFS complet IDFM. D'autres lignes du même
  réseau/exploitant (ex. 3103, Keolis Portes et Val de Brie) sont bien présentes — ce n'est donc pas
  tout le réseau "Pays Briard" qui manque, juste cette ligne précise. **Cause confirmée
  (2026-08-23)** : les fichiers GTFS bruts sont en fait bien présents en local
  (`documentation/IDFM-gtfs/csv/` — erreur de vérification précédente, seul le dossier parent
  avait été inspecté, pas ce sous-dossier) ; vérifié directement dans `routes.txt`/`trips.txt` du
  flux GTFS actuel : **aucune entrée du tout pour `C01058`**, ni dans les routes ni dans les trips.
  La ligne n'a donc purement et simplement aucun service programmé dans l'instantané GTFS courant,
  bien que listée `active` dans le référentiel administratif — écart entre les deux sources,
  probablement une ligne suspendue/à service très restreint (scolaire ?) non répercutée dans le
  référentiel. Rien à corriger côté import (le comportement actuel, ne pas importer une ligne sans
  aucun trip réel, est correct).
- Lisibilité des badges de ligne — **fait (2026-08-20)** : remplacé par la classe `.pastille-ligne`
  (`assets/styles/app.scss`), un carré de couleur (`--ligne-couleur`) suivi du nom de la ligne en
  texte noir sur fond blanc, jamais de texte dans le carré. Anciennes classes `.ligne-badge`/
  `.ligne-badge-sm` supprimées, 19 templates convertis (30 emplacements). Voir
  `documentation/commande.md`.
- Tracés de bus vs vol d'oiseau (signalé le 2026-08-17, capture d'écran `/trajet` à l'appui) —
  **investigué le 2026-08-22**, script de scan PHP réimplémentant l'heuristique de
  `assets/js/trajet-carte.js` (`projeterSurSegment`/`projeterSurLigne`/score, seuil `150m`) sur les
  62038 tronçons de bus ayant les coordonnées des 2 côtés. Verdict : 61540 ont une `Ligne.trace` ;
  61102 passent l'heuristique, **438 échouent** (0,7%), en deux populations bien distinctes :
  1. **280 cas "proches" (150-1000m, dont 232 entre 150-200m)** : vraisemblablement juste la marge
     normale du seuil de 150m (imprécision GPS, virage serré) — impact visuel probablement mineur,
     pas creusé plus loin (resserrer/assouplir le seuil resterait à faire si jugé utile après
     vérification visuelle d'un échantillon).
  2. **158 cas "loin" (1000m à 25km)** — concentrés sur **seulement 4 Ligne** (pas 158 lignes
     différentes), toutes identifiées précisément :
     - `Soir Domont` (#2731, réseau Vallée de Montmorency, 88 troncons) et `TàD Eaubonne Domont`
       (#2711, même réseau, 36 troncons) : `TransportSubmode = demandAndResponseBus` dans
       `referentiel-des-lignes.csv` — ce sont des services de **Transport à la Demande**, sans
       itinéraire fixe réel. Le tracé GPS fourni par IDFM pour ces lignes ne correspond qu'à un
       parcours théorique/partiel, pas aux arrêts réellement desservis (bbox du tracé et bbox des
       stations totalement disjointes, ~15-25km d'écart) — **pas un bug**, comportement attendu vu
       la nature du service.
     - `Remplacement Transilien H` (#2538, réseau Transilien/SNCF, 22 troncons) : `Type =
       REPLACEMENT_LINE_TYPE` — un service de bus de substitution (remplace le train, ex. travaux),
       sans itinéraire routier fixe unique non plus par nature. Même conclusion : pas un vrai bug de
       données à corriger.
     - `1412 (ex 95-03B)` (#1696, réseau Val Parisis, `expressBus`, 12 troncons) : **seule vraie
       ligne de bus régulière du lot, et seul cas confirmé de données réellement incomplètes** — son
       `Ligne.trace` (2 composantes, 162 points) ne couvre qu'une partie de la zone desservie par ses
       30 Station (bbox du tracé plus étroite que celle des stations). Volume marginal (12/62038,
       0,02%) : pas de correctif de code identifié (les données GPS manquantes ne peuvent pas être
       reconstruites sans nouvelle source), et le repli ligne droite reste la seule option sur ces
       12 troncons précis en l'état des données IDFM disponibles.
  En résumé : le signalement initial pointait un phénomène réel, mais qui touche un nombre de
  lignes très restreint (4 sur ~1400 lignes de bus), dont 3 s'expliquent par la nature même du
  service (TAD/remplacement) plutôt que par un bug — pas de changement de code effectué à ce
  stade.
- ZdC absents de `zdc_coordonnees.csv` (signalé le 2026-08-22, arrêt "Les Tournelles" cherché sur
  Google Maps par l'utilisateur, semblant situé à "Hautefeuille") — **fait (2026-08-24)**.
  "Hautefeuille" n'est pas la rue parisienne du même nom mais une petite commune de Seine-et-Marne
  (77224) ; l'arrêt existe dans le référentiel officiel IDFM (`zones-d-arrets.csv`, ZdC `67756`).
  **Ampleur corrigée** (la mesure du 2026-08-22 comptait à tort les lignes brutes du référentiel —
  des ZdA, arrêts physiques — au lieu des ZdC distinctes) : `zones-d-arrets.csv` référence
  **15539 ZdC distinctes** (colonne `ZdCId`, pas le nombre de lignes), contre 13752 déjà connues —
  un écart réel de **1789 ZdC (11,5%)**, pas 4250/23,6% comme annoncé initialement.
  Cause confirmée avec les fichiers GTFS bruts (disponibles en local, `documentation/IDFM-gtfs/csv/`) :
  ces 1789 ZdC sont **totalement absentes du flux GTFS actuel** (`stops.txt`) — vérifié
  précisément, aucune n'apparaît même sous un autre `location_type` — donc pas un bug
  d'extraction : elles n'ont simplement plus de service programmé dans l'instantané GTFS actuel,
  tout en restant de vrais arrêts référencés officiellement.
  Récupérées via `documentation/scripts/extraire_zdc_manquants_lambert93.php` : `zones-d-arrets.csv`
  donne leurs coordonnées en Lambert-93 (EPSG:2154, colonnes `ZdAXEpsg2154`/`ZdAYEpsg2154`) —
  reprojetées vers WGS84 via la formule officielle IGN (Lambert conforme conique, ellipsoïde
  GRS80), **validée contre 30 ZdC déjà connus des deux façons avant tout import** (écart moyen 8m,
  max 66m — cohérent avec un simple décalage ZdA/ZdC, pas une erreur de formule).
  `app:importer-zdc-manquants` (nouvelle commande, idempotente) : **1789 Station créées** (label,
  ville, coordonnées, `code_externe`), sans Desserte/Ligne rattachée (pas de service actif à
  rattacher) — apparaissent sur `/ville` et la carte, pas dans le calculateur de trajet. Vérifié
  aucun doublon créé (`app:fusionner-stations-dupliquees --dry-run` → 0 paire). `app:importer-villes`
  relancée : 1755 des 1789 nouvelles Station rattachées à leur Ville (le reste hors Île-de-France ou
  sans correspondance de nom, même limite que d'habitude). "Les Tournelles" vérifiée concrètement :
  Station #28458, ville "Hautefeuille", coordonnées correctes.
- Quais décalés (ex: Liège sur la ligne 13) — **fait (2026-08-21)** : ajout de
  `TronconDesserte::dureeReelleSecondes` (nullable, uniquement significatif côté "Départ"),
  `Troncon::dureeReelleSecondes` restant le repli symétrique (`TrajetFinder::construireGraphe()`
  fait `COALESCE(TronconDesserte, Troncon)`). En creusant `troncon_durees.csv`, découverte que le
  cas n'est pas isolé : **661 des 772 paires (86%)** ont un aller et un retour présents ET
  différents dans le CSV source (déjà là depuis le début, seulement jamais exploité — le sens
  précis était perdu par `$durees[$nomA][$nomB] ?? $durees[$nomB][$nomA]`, qui traitait les 2 sens
  comme interchangeables). `ImporterDureesTronconCommand` réécrit pour écrire les deux sens quand
  ils existent. Vérifié sur Liège : Clichy→Saint-Lazare 2,6 min (64+89s) vs Saint-Lazare→Clichy
  2,2 min (65+69s), désormais bien distingués par le calculateur. **Limite résiduelle** : seul
  `troncon_durees.csv` (réseau historique métro/RER/tram, 772 paires, la donnée déjà utilisée
  avant cette session) a ce niveau de détail directionnel ; les topologies bus/RER D/Transilien
  construites cette même session (2026-08-20/21) utilisent une durée médiane déjà fusionnée par
  sens à l'extraction (`sort($paire)` avant calcul) — pas encore réextraites en gardant la
  directionnalité, resterait à faire si jugé utile.
- Lignes de bus : plage 20-299 complète depuis le 2026-08-17 (voir `documentation/commande.md`).
  Les 16 lignes non-RATP restantes de la plage 101-299 sont faites : ATM Croix du Sud
  (179/189-191/194-195/289-290), Keolis Grand Paris Vallée de la Marne (206-207/209/211-213/220),
  et la 282 (aujourd'hui "Keolis Grand Paris Seine Orly", TODO.md la notait encore sous son ancien
  nom "Keolis Ouest Val-de-Marne"). La 262 (Keolis Argenteuil) de la note originale n'existe plus
  sous ce numéro dans le référentiel actuel (réseau intégralement renuméroté en série "64xx")  —
  pas importée, pas de correspondance fiable trouvée plutôt que deviner. Au passage : un bug
  d'algorithme identique à celui trouvé sur le RER C (coercition de clés de tableau PHP cassant
  Dijkstra) a été audité sur `extraire_troncons_bus_autres_operateurs.php` — présent dans le code,
  mais sans impact visible sur les données déjà construites (les bus n'ont quasiment jamais de
  missions semi-directes à filtrer, contrairement au RER), donc pas de reconstruction nécessaire ;
  corrigé uniquement dans le nouveau script utilisé pour ces 16 lignes.
  Reste du réseau bus (~1300 lignes hors 20-299) — **fait (2026-08-20)** : la limite "trop
  volumineux pour un seul passage" est levée en lisant la liste des lignes à traiter directement en
  base (toute Ligne Bus/Car sans aucun Troncon) au lieu d'une liste tapée à la main — l'algorithme
  d'extraction GTFS + réduction géométrique était déjà générique. 1167 lignes traitées en un seul
  passage (`extraire_troncons_bus_reste.php` + `app:construire-topologie-bus-autres-operateurs`),
  24120 troncons créés. Le nombre de Desserte isolées (sans aucun Troncon, tous modes confondus)
  passe de 24270 à 379 (-98,4%), vérifié identique en local et en prod. Résiduel (379) réparti en
  Train (331), RER (28), Bus (13), Téléphérique (5), Funiculaire (2) — cas marginaux (lignes
  fermées/spéciales), non traités à ce stade. Voir `documentation/commande.md`.
- Téléphérique (Câble A - Créteil, 5 stations) et Funiculaire de Montmartre (2 stations) — **fait
  (2026-08-21)** : topologie construite (`extraire_troncons_telepherique_funiculaire.php`, 5
  troncons, même commande générique que le bus). Mais **bug plus profond découvert et corrigé au
  passage** : `Ligne::getModeFiltre()`/`TrajetFinder` ne reconnaissaient que Métro/Tramway/RER/Bus,
  donc ces 2 modes étaient invisibles au calculateur de trajet quel que soit le filtre coché (une
  arête de mode `null` n'est jamais incluse dans `TrajetFinder::construireGraphe()` dès qu'un
  filtre non-vide est actif — et le filtre par défaut au chargement de la page EST non-vide, les 5
  cases cochées). Ajout de `telepherique`/`funiculaire` comme modes reconnus partout où le filtre
  existe (entité, service, 3 Repository, 4 controleurs, 2 templates). Vérifié : Câble A utilisable
  et choisi par Dijkstra quand il est le plus rapide (La Végétale→Valenton, 4 min direct) ;
  Funiculaire correctement concurrencé par une alternative bus plus rapide sur le seul trajet testé
  (comportement voulu, pas un bug). Isolées : Téléphérique et Funiculaire à 0 des deux côtés.
- RER D (28 Desserte isolées) — **fait (2026-08-21)**. Premier diagnostic erroné (attribué aux
  "Stations dupliquées" ci-dessus) corrigé après lecture de `ConstruireTopologieRerCommand` : la
  vraie cause est exactement celle déjà documentée plus haut ("Lignes à embranchements complexes",
  maillage Évry/Corbeil/Juvisy découvert le 2026-08-09) — confirmé que les 28 Desserte isolées
  correspondent pile à cette zone. `documentation/scripts/donnees-extraites/troncons_rer.csv`
  contenait déjà les 60 arêtes de la ligne D (dont celles du maillage), simplement jamais
  importées par `ConstruireTopologieRerCommand::construireRerD()` car son modèle Direction/tronçon
  suppose un arbre et ne peut pas représenter un graphe à cycles. Solution retenue : nouvelle
  commande `app:construire-maillage-rer-d` (`src/Command/ConstruireMaillageRerDCommand.php`) qui
  importe uniquement les `Troncon`/`TronconDesserte` manquants (rattachement par label de Station,
  comme la commande existante), **sans** créer de `Direction`/`Mission` — `TrajetFinder::construireGraphe()`
  ne lit que `Troncon`/`TronconDesserte` (vérifié, aucune référence à Direction/Mission dans sa
  requête), donc ça suffit à rendre le maillage utilisable par le calculateur sans résoudre le
  problème plus dur (et non nécessaire ici) de représenter un cycle dans un modèle pensé pour un
  arbre. Idempotente (ne recrée pas les arêtes déjà là). 31 troncons créés (29 déjà présents). RER
  isolé : 28 → 0. Vérifié : Melun → Juvisy trouve maintenant un trajet RER D direct (12 étapes,
  35,7 min, 0 correspondance) au lieu d'être introuvable.
- Transilien H/J/K/L/N/P/R/U (252 Desserte isolées) — **fait (2026-08-21)**, sur demande explicite
  de continuer après le RER D. Même méthode que le RER (`extraire_troncons_transilien.php`, PHP
  autonome basé sur `stops.txt`/haversine plutôt que le script Python original qui dépend d'un
  référentiel Lambert-93 externe au dépôt). 7 des 8 lignes ont un excédent d'arêtes par rapport à
  un arbre pur (embranchements + au moins une vraie boucle connue sur H, Argenteuil/Ermont) :
  plutôt que d'auditer chaque ligne une par une comme pour le RER, choix (comme pour le RER D) de
  construire uniquement `Troncon`/`TronconDesserte` (`app:construire-topologie-transilien`, nouvelle
  commande), sans `Direction`/`Mission`. Rattachement par label de Station au sein de chaque Ligne ;
  4 paires nécessitaient une correspondance manuelle (tiret manquant côté DB : "Neuville -
  Université", "Saint-Nom-la-Bretèche - Forêt de Marly", "Viroflay - Rive Droite", "Nemours -
  Saint-Pierre"). 308 troncons créés. **Bug supplémentaire découvert et corrigé** : `Train` n'était
  pas non plus reconnu par `Ligne::getModeFiltre()`/`TrajetFinder` (même bug de fond que
  Téléphérique/Funiculaire) — ajout de `train` comme 8e mode reconnu partout où le filtre existe.
  Vérifié : Luzarches → Persan-Beaumont (2 branches de la ligne H) trouve un trajet H direct en
  filtrant sur Train seul (25,3 min, 8 étapes) ; avec tous les modes cochés, un bus plus rapide de
  1,3 min est choisi à la place (comportement Dijkstra correct, pas un bug). Desserte isolées
  toutes lignes : 344 → 92 (Train résiduel : TER/V/CDG VAL/ORLYVAL, hors périmètre Transilien).

- Normaliser le champ `ville` (varchar libre) en table `Ville` (signalé le 2026-08-22) — **Station
  fait (2026-08-23)** : demande utilisateur de créer une entité `Ville` (id, label, frontières
  géographiques) et une clé étrangère depuis `Station`, pour pouvoir afficher sur la carte les
  limites d'une commune ou la colorier entièrement. Frontières GPS récupérées le 2026-08-22 depuis
  l'API officielle `geo.api.gouv.fr` (contour GeoJSON par commune), périmètre Île-de-France
  seulement (choix explicite de l'utilisateur) — `documentation/geo-communes/communes-
  {75,77,78,91,92,93,94,95}.geojson`, 1266 communes, ~6,1 Mo.
  Entité `Ville` créée (`label`, `codeInsee` unique, `frontiere` en JSON — même convention que
  `Ligne::trace`, `codesPostaux` en JSON ajouté le 2026-08-23 après avoir été oublié au premier
  passage bien que présent dans le GeoJSON depuis le départ) + `Station::villeRef` (`ManyToOne`,
  nullable) — **choix délibéré : additif, pas
  un remplacement** du champ `ville` (varchar) existant, qui reste utilisé tel quel par
  `TrajetController`, `templates/station/show.html.twig` et `ImporterPlansSecteurCommand`
  (déduction du département depuis le texte brut) : les casser n'était pas demandé, et `villeRef`
  apporte une capacité nouvelle (frontière réelle) plutôt que de remplacer une donnée déjà utilisée
  ailleurs. Nouvelle commande `app:importer-villes` (upsert des `Ville` par `codeInsee`, puis
  rattachement de chaque `Station` par correspondance de nom depuis son `ville` texte libre) : 4
  corrections manuelles pour des communes renommées/fusionnées depuis l'ancien import (Saint-Ouen →
  Saint-Ouen-sur-Seine, Chesnay-Rocquencourt → Le Chesnay-Rocquencourt, Herblay → Herblay-sur-Seine,
  Evry-Courcouronnes → Évry-Courcouronnes), et désambiguïsation par test point-dans-polygone (pas
  une simple distance) pour les 4 noms de commune réellement homonymes (Blandy, Marolles-en-Brie,
  Mondreville, Saint-Martin-des-Champs, 12 Station concernées) plutôt qu'un choix arbitraire.
  Résultat identique local/prod : **1266 Ville, 13529 Station rattachées, 144 sans correspondance**
  (commune hors Île-de-France — Chartres, Sens, Château-Thierry... — absente par choix de
  périmètre, pas un bug), **0 homonyme non tranché**.
  **Étendu aux 4 autres entités (2026-08-24)** : `Defibrillateur`, `EquipementArret`,
  `PointDeVente`, `Utilisateur` ont désormais aussi `villeRef` (migration `Version20260824100000.php`).
  `app:importer-villes` refactorisée (logique de rattachement extraite en une méthode générique
  `rattacherEntites()`, réutilisée pour les 5 entités plutôt que dupliquée). Désambiguïsation des
  homonymes par position : ces 4 entités ont leurs propres `latitude`/`longitude` (pas besoin de
  passer par une Station liée), sauf `Utilisateur` (aucune coordonnée disponible — les cas
  homonymes ambigus y restent simplement non tranchés).
  **Bug trouvé et corrigé avant tout déploiement** : le taux de rattachement de `PointDeVente`
  était anormalement bas (24/2032, 1,2%) — cause : son champ `ville` est stocké **tout en
  majuscules** ("CRÉTEIL") contrairement aux autres entités et au référentiel geo.api.gouv.fr
  ("Créteil"), et la comparaison était sensible à la casse. Corrigé en normalisant la comparaison
  (`mb_strtoupper`) plutôt que la donnée source (laissée intacte). Résultat après correction :
  PointDeVente 2030/2032 (99,9%), Defibrillateur 406/448 (90,6%), EquipementArret 40423/40511
  (99,8%), Utilisateur 0/0 (table vide en dev).
  **UI ajoutée (2026-08-23)** : page `/ville` (liste, 1266 communes) et `/ville/{id}` (fiche —
  Stations rattachées + Lignes concernées, classées en 3 catégories via
  `VilleRepository::trouverLignesConcernees()` : **entièrement dans la ville** (toutes les Desserte
  de la Ligne y sont), **un bout hors de la ville** (au moins une extrémité de la Ligne — Desserte
  ne touchant qu'un seul Troncon distinct, terminus de ligne/branche — y est, mais pas toutes ses
  Desserte), **traversée simple** (des Desserte y sont mais aucune n'est une extrémité — la ligne
  entre et sort sans qu'aucun bout n'y soit). Vérifié concrètement sur Paris (878 Station, 39/160/50
  Ligne dans les 3 catégories) et sur le cas limite Ligne 1 métro : les deux termini (La Défense,
  Château de Vincennes) semblaient a priori hors Paris, mais "Château de Vincennes" est en réalité
  rattachée à "Paris 12e" dans la donnée source elle-même (`Station.ville`, jamais modifiée) — la
  ligne est donc correctement classée "un bout hors" et pas "traversée", confirmant la justesse de
  la classification plutôt qu'un bug.
  Page `/ligne` (index) complétée symétriquement : liste des Villes concernées affichée sous chaque
  Ligne (`LigneRepository::trouverVillesParLigne()`, une seule requête groupée pour la page entière,
  pas de N+1). Toujours aucun affichage de frontière GPS sur une carte à ce stade (donnée
  `Ville::frontiere` disponible mais pas encore exploitée visuellement).
- Filtre alphabétique + recherche texte sur tous les index paginés (signalé le 2026-08-23) —
  **fait (2026-08-24)**, sur demande utilisateur : sur chaque page d'index avec pagination, une
  barre de lettres (A-Z, cliquer sur "C" ne montre que les entrées commençant par C) et un champ de
  recherche texte. **34 controllers** utilisent `PaginatorInterface` (vérifié via grep), traités
  ainsi :
  - **21 avec un seul champ texte pertinent** (Ville, Station, StyleAcces, Utilisateur,
    PoleEchange, Plan, SanisettePublique, Sanitaire, PointDeVente, FontaineEau, Defibrillateur,
    Service, Gestionnaire, TypeTroncon, TypeTransport, TypeMateriel, StyleStation, StatutTache,
    ProjetArret, EquipementArret) : mécanisme réutilisable
    (`src/Repository/FiltreAlphabetTrait.php` + `templates/tools/filtre_alphabet.html.twig`),
    appliqué de façon mécanique (filtre par `LIKE 'lettre%'`/`LIKE '%recherche%'` sur le champ déjà
    utilisé pour le tri, sans changer l'ordre existant).
  - **2 avec un filtre de recherche déjà existant** (Ligne via `tools/filtre_liste.html.twig`,
    Accès via son propre formulaire) : paramètre `lettre` ajouté directement à leur
    `creerRequeteFiltree()` existante, barre de lettres seule affichée (option
    `masquerRecherche: true` du partial, pour ne pas dupliquer le champ de recherche déjà présent).
  - **11 non applicables**, documentés ici plutôt que forcés : `Troncon` et `Desserte` (recherche
    texte déjà existante mais triés par id/relation, pas de champ label propre pertinent pour une
    lettre), `PlanRegion` (tri par ordre numérique), `MaterielLigne`/`PositionRame` (relations
    Ligne+Station/Materiel, pas de label propre), `Materiel`/`PeriodeOuverture`/`Sortie`/`Correspondance`
    (`creerRequeteAvecDetails()`, pas de champ texte simple), `Etape`/`Tache` (triés par date),
    `DocumentLigne` (trie par `ligne.label` — pourrait bénéficier d'un filtre sur ce champ si
    besoin un jour, non fait par manque de cas d'usage clair).
  Vérifié en navigateur : `/gestionnaire?lettre=K` (12 "Keolis..."), `/ligne?lettre=A` (RER A,
  AUDONIE, AS...). `php bin/phpunit` (137), `npx jest` (51) : tout passe.
- "À voir à proximité" (`PointInteret`) — **fait (2026-08-23)**, sur idée utilisateur (section façon
  Wikipédia listant les lieux remarquables proches d'une station). Source : le champ `to_name` de
  `positionnement-dans-la-rame.csv` (déjà exploité pour les conseils de position, voir plus haut)
  contient parfois le nom d'un vrai lieu plutôt qu'une simple adresse de rue quand la sortie y mène
  directement (ex. "Hôpital Kremlin Bicêtre", vérifié différent du `Acces.label` déjà connu pour ce
  même accès) — donnée gratuite, déjà présente, jamais exploitée jusque-là.
  `documentation/scripts/extraire_points_interet.php` (nouveau) : filtre par expression régulière +
  denylist explicite (adresses de rue/avenue/place, génériques "Gare routière"/"Centre Commercial"
  sans nom propre) sur les 1018 `to_name` distincts de type `access_point` — **87 paires (ZdC, lieu)
  retenues**, committées dans `documentation/scripts/donnees-extraites/points_interet.csv`.
  `PointInteret` (nouvelle entité, `label` unique + `stations` ManyToMany — un même lieu peut être
  proche de plusieurs Station, ex. Forum des Halles) + `app:importer-points-interet` (nouvelle
  commande) : **85 PointInteret créés, 87 rattachements Station**. Affiché sur la fiche Station
  (`/station/{id}`, section "À voir à proximité"). Vérifié sur `/station/69` (Gambetta) :
  "Père-Lachaise" et "Hôpital Tenon" — cohérent géographiquement.
  Piste explorée puis écartée : `point_de_vente` (commerces tabac-presse) — source différente, déjà
  bien structurée mais hors périmètre sémantique (pas des lieux remarquables).
  Idée séparée traitée au passage (clarification "fait au mieux") : `StationRepository::rechercherParLabel()`
  matche désormais aussi par nom de `Ville` (`Station::villeRef`) en complément du label de Station,
  toujours priorisé après un vrai match direct — permet de taper un nom de commune dans le
  calculateur de trajet et retrouver ses Station même si aucune ne porte ce nom exact (vérifié :
  "Andrezel" → "Salle des Fêtes", son unique arrêt ; pas de régression sur les recherches
  existantes).

## Lignes Transilien V/P/R — fait (2026-08-17)

Cette note datait d'avant qu'`app:importer-reseau-complet` ne tourne sur l'ensemble du réseau :
en y regardant à nouveau, les Ligne V/P/R existaient déjà (avec leurs Station/Desserte réelles,
7/24/32 stations), simplement jamais reliées à leur matériel roulant partagé avec le RER. Lien
`MaterielLigne` ajouté : Z 5600/8800/20500/20900 → V, Z 57000/57400 → R, Z 50000 → P. Comme pour
`app:importer-reseau-complet` en général, ces lignes n'ont pas de tronçons/parcours (seulement
Ligne/Station/Desserte) — voir `documentation/commande.md` pour le détail.

## Ligne.codeExterne incohérent pour le métro — fait (2026-08-17)

Voir `documentation/commande.md` pour le détail. Au moment d'y regarder, les 16 `Ligne` de métro
avaient en fait `codeExterne` NULL (pas une valeur fausse comme le laissait entendre l'ancienne
note ci-dessous — les doublons décrits avaient déjà été nettoyés dans une session antérieure, sans
que `codeExterne` soit repeuplé derrière). Rempli via `referentiel-des-lignes.csv` (16/16 labels
sans ambiguïté, recoupé indépendamment avec `routes.txt` GTFS `route_type=1`). Un vrai risque de
régression a été trouvé et corrigé au passage : `app:construire-positions-rame` désambiguïsait un
label de ligne partagé avec des lignes de bus homonymes (ex. bus "7") en préférant la `Ligne` sans
`codeExterne` — un signal qui devenait faux et dangereux une fois `codeExterne` rempli pour de bon.
Remplacé par un filtre explicite sur `TypeTransport = 'Métro'`. `app:importer-traces-lignes` et
`app:importer-documents-lignes` utilisaient le même repli par label mais seulement en solution de
secours après un essai par `codeExterne` : celui-ci fonctionne maintenant directement pour le
métro, le repli fragile n'est simplement plus jamais atteint pour ces lignes (pas de changement de
code nécessaire). Effet de bord positif constaté : 2 `DocumentLigne` auparavant mal rattachés à une
ligne de métro (probablement via l'ancien repli fragile) se sont correctement rattachés à leur
vraie ligne lors du réimport.

Note découverte au passage, hors périmètre de cette tâche : les lignes de métro 15 et 18 (déjà
dans `referentiel-des-lignes.csv`/GTFS actuel) ne sont pas encore importées dans la base.

## Desserte.styleStation souvent NULL (signalé 2026-08-24) — Nord-Sud fait, CMP fait (2026-08-25)

`StyleStation` a 7 valeurs en base (mouton [en réalité "Mouton-Duvernet", style ~1968-1974 à 2
tons non biseautés], motte [style Andreu-Motte 1975-1984], renouveau du métro, CMP, Nord Sud,
Ouï-dire [après 1984], Décor unique), partiellement peuplées à la main sur les Desserte Métro/RER.
Demande utilisateur : les styles "Nord Sud" et "CMP" sont "identifiables" (fait historique
documenté), contrairement aux styles de rénovation (motte, renouveau, Ouï-dire, mouton, décor
unique) hors périmètre de cette tâche.

**Nord-Sud (Ligne 12 + 13 nord) : vérifié exhaustivement, quasi complet.** Recherche menée via
`fr.wikipedia.org` (wikitext brut des articles Ligne 12, Ligne 13, et l'article de synthèse
"Aménagement des stations du métro de Paris") : la compagnie Nord-Sud n'a construit que 2 lignes
(Ligne A 1910-1916 → Ligne 12 entière Porte de Versailles-Porte de la Chapelle ; Ligne B 1911-1912
Saint-Lazare-Porte de Saint-Ouen → branche nord de l'actuelle Ligne 13 seulement — la branche sud
Châtillon-Montrouge/Vavin/Denfert vient de l'ancienne Ligne 14 CMP 1937, fusionnée en 1976, donc
jamais Nord-Sud). Les 20 Desserte déjà taggées "Nord Sud" en base couvrent exactement ce
périmètre. Sur les 6 Desserte Ligne 12 restées NULL, 3 sont des stations 2010s-2020s hors
périmètre historique (Front Populaire, Mairie d'Aubervilliers, Aimé Césaire — construction trop
récente) ; 1 (Assemblée Nationale, ex-Chambre des Députés) a bien été Nord-Sud à l'origine mais son
quai a été entièrement transformé depuis en décor d'art contemporain rotatif (carrossage dès les
années 1950, fresques Jean-Charles Blais 1990, puis décor Daney/mi+ro/Toury 2016) — plus aucune
trace Nord-Sud visible sur le quai, seul le fer forgé des accès et la voûte semi-elliptique
(structurel, pas décoratif) restent caractéristiques ; laissé NULL, candidat plausible pour un futur
tag "Décor unique" mais hors demande. **1 correction apportée** : *Porte de Versailles* (Desserte
id 337) était NULL alors que sa page Wikipédia est explicite — quais Nord-Sud d'origine mêlés au
style Andreu-Motte adouci, exactement le même cas de figure que *Pasteur* (même ligne) et *Porte de
Clichy* (Ligne 13), déjà tagués "motte" en base pour ce même trio documenté ("l'une des trois du
réseau à mêler ces deux styles décoratifs") → tagué "motte" par cohérence. Ligne 13 : les 6 Desserte
NULL restantes (Châtillon-Montrouge, Les Agnettes, Les Courtilles, Miromesnil, Saint-Denis Université,
Champs-Élysées-Clemenceau) sont toutes des extensions 1973-2008, hors périmètre historique — rien à
faire.

Point de vigilance trouvé en passant, **non corrigé** (hors périmètre demandé, qui était de remplir
les NULL, pas d'auditer l'existant) : l'article de synthèse liste nommément les stations encore sous
carrossage métallique (masquant tout décor d'origine) *en date d'octobre 2025* — dont *Convention*
et *Falguière* (Ligne 12), toutes deux déjà taguées "Nord Sud" en base. Incohérence potentielle
(décor Nord-Sud réel mais actuellement invisible sous le carrossage) à trancher si quelqu'un
retravaille cette table : la convention actuelle de la base semble être "style historique d'origine"
plutôt que "décor visible aujourd'hui", auquel cas ces 2 tags restent corrects tels quels.

**CMP : recherche complète menée sur les 11 lignes restantes (2026-08-25), fait.** Contrairement à
Nord-Sud (2 lignes, dates d'ouverture nettes), CMP a construit la quasi-totalité du réseau d'origine
(Lignes 1 à 11) — la faïence blanche biseautée y était le standard par défaut, pas une décoration
remarquable. Les 5 Desserte déjà taguées "CMP" (Corentin Celton L12, Porte de Vanves L13, Victor
Hugo L2, Porte d'Ivry L7, Faidherbe-Chaligny L8) reposent chacune sur un fait très spécifique et
documenté au cas par cas (ex. Victor Hugo : plaques de nom en faïence de style CMP
"entre-deux-guerres", décrit par Wikipédia comme "un cas unique et anachronique sur la ligne 2"
puisque les stations de cette ligne datent d'avant 1903).

Méthode : vérification individuelle (page Wikipédia de chaque station, wikitext brut) des 96
Desserte Métro encore NULL sur les 11 lignes 1,2,3,3bis,4,5,6,7,7bis,8,9,10,11 (RER exclu, hors
périmètre historique CMP/Nord-Sud). Ligne 4 exclue en bloc (0 vérification individuelle nécessaire)
: automatisation complète 2017-2023, chaque quai nécessairement retouché à cette occasion. Critère
retenu, par cohérence avec les 5 exemples déjà en base : exclu dès qu'un siège de marque est
mentionné (Motte, Akiko, Ouï-dire, coque, smiley...) ou qu'un programme de rénovation nommé est
explicitement cité pour ce quai précis (carrossage, Renouveau du métro/Gaudin, Ouï-dire, Mouton) —
signe que le quai a été retouché, même si le carrelage d'origine subsiste par endroits. Retenu
seulement si soit (a) explicitement regroupée par Wikipédia avec des stations déjà CMP en base
("l'une des N seules du réseau à..."), soit (b) aucun programme nommé ni siège de marque mentionné,
mobilier générique (bancs en lattes de bois, tubes fluorescents simples). **Résultat : seulement 3
nouvelles Desserte qualifiées** (sur 96 vérifiées) :
- *Europe* (Ligne 3, Desserte id 58) — critère (b), aucune rénovation de quai mentionnée (seuls les
  couloirs rénovés en 2000), bancs en lattes de bois, éclairage simple.
- *Porte des Lilas* (Ligne 3 bis, Desserte id 397) — critère (a), Wikipédia la cite explicitement
  comme "l'une des trois seules du réseau" à associer ce modèle de rampe lumineuse à la décoration
  CMP, avec *Porte d'Ivry* (Ligne 7) et *Porte de Vanves* (Ligne 13) — les 2 autres déjà taguées CMP
  en base, confirmation directe et sans ambiguïté.
- *Bercy* (Ligne 6, Desserte id 148) — critère (b), aucune rénovation de quai mentionnée, bancs en
  lattes de bois, éclairage simple (à ne pas confondre avec le quai Ligne 14 de la même station,
  d'architecture entièrement différente et hors sujet).

Rendement volontairement bas (3/96) : la plupart des quais encore NULL ont en réalité déjà été
retouchés (souvent plusieurs fois) par un programme de rénovation documenté mais pas encore répercuté
dans cette base — rester NULL est donc plus juste que de deviner CMP par défaut. Point de vigilance
trouvé en passant, non traité (hors périmètre CMP/Nord-Sud) : plusieurs stations mélangent
authentiquement un détail CMP entre-deux-guerres avec un habillage Motte/Ouï-dire plus récent (ex.
Stalingrad/Jaurès/Laumière Ligne 5, Bonne-Nouvelle Ligne 8) sans que la convention de cette base soit
totalement cohérente sur laquelle des deux étiquettes doit l'emporter dans ce cas (Victor Hugo déjà
en base tranche pour CMP, Pasteur/Porte de Clichy/Porte de Versailles tranchent pour motte) — non
retouché ici, à trancher si quelqu'un reprend ce sujet en détail.

## Page /ligne/{id} — pastilles collées au nom + ordre des stations en maillage, fait (2026-08-25)

Signalé par l'utilisateur : "pastilles de correspondance collées au nom de la station, et l'ordre
des stations pas toujours le vrai cheminement physique sur les lignes en maillage (RER D vérifié
concrètement)".

**Pastilles collées — fait.** Cause racine trouvée en inspectant `/ligne/1` en vrai (compte de
test) : `Ligne::getCorrespondances()` renvoyait TOUTES les Ligne desservant la même Station, bus et
Noctilien compris — à un gros pôle comme "La Défense", ça fait 26 correspondances (bus/nuit/RER/
tram/train mélangés) avant même d'arriver au nom de la station, qui se retrouvait écrasé/coupé en
plein mot faute de `flex-wrap` sur le conteneur. Corrigé en deux temps :
1. `Ligne::getCorrespondances()` exclut désormais les Ligne de type "Bus" (le détail complet, bus
   compris, reste consultable sur la fiche Station elle-même, table "Dessertes" — rien n'est perdu,
   juste pas répété ici). "La Défense" passe de 26 correspondances à 5 (A, E, L, T2, U).
2. `templates/ligne/show.html.twig` : `flex-wrap` ajouté au conteneur de la ligne de station, pour
   que les pastilles passent proprement à la ligne suivante si jamais elles ne tiennent pas, plutôt
   que d'écraser le nom. Ordre conservé tel que déjà demandé : pastilles de correspondance d'abord,
   nom de la station ensuite.
Vérifié dans le navigateur (compte de test) sur `/ligne/1` : Châtelet affiche proprement "4 7 11 14
Châtelet", plus aucun débordement. `php bin/phpunit` (137) et `npx jest` (51) : tout passe.

**Ordre des stations en maillage — fait (2026-08-25), sur la RER D.** `Ligne::getParcoursSegments()`
suppose une topologie en arbre : un seul terminus sert de racine, parcours reconstruit
récursivement via `Desserte::getTronconsDepart()`. Diagnostic précis mené via un script ponctuel
(détection de cycle par Union-Find sur le graphe Troncon de chaque Ligne) : la RER D a exactement
**2 vrais cycles** (59 nœuds/Desserte, 60 arêtes/Troncon — 2 de plus qu'un arbre), localisés très
précisément entre *Villeneuve-Saint-Georges* et *Juvisy*/*Viry-Châtillon* : deux itinéraires
physiques réels et distincts existent entre ces points (voie courte via Vigneux-sur-Seine, voie
longue via Montgeron-Crosne/Grigny Centre/Viry-Châtillon d'un côté ; et Corbeil-Essonnes via
Grand Bourg vs via Le Bras de Fer/Évry-Courcouronnes de l'autre) — pas une erreur de données, la
géographie réelle du RER D est bien ainsi. Ce même genre de cycle existe sur ~30 autres lignes de la
base (bus pour l'essentiel, boucles de terminus simples déjà bien gérées par le mécanisme
"rejoint").

Deux causes distinctes trouvées et corrigées :
1. **Vrai bug** (pas seulement un besoin d'étiquette) : `getParcoursSegments()` créait des branches
   "fantômes" ne contenant qu'un seul arrêt déjà visité (`rejoint=true` dès le premier élément) —
   artefact de l'exploration du même cycle par un chemin différent de celui déjà parcouru. Corrigé :
   ces branches à zéro apport sont maintenant silencieusement ignorées plutôt qu'affichées comme un
   encart vide et confus (2 occurrences trouvées sur la RER D : "Grigny Centre" et "Montgeron -
   Crosne" affichés seuls avec juste un badge "rejoint").
2. **Étiquetage manuel demandé par l'utilisateur** : nouveau champ nullable `Troncon.varianteMaillage`
   (migration `Version20260825201506`), posé uniquement sur le Troncon qui referme un vrai maillage
   — affiché dans le badge "rejoint" à la place du texte générique. Posé sur les 2 jonctions
   réelles de la RER D : "Voie via Le Bras de Fer / Évry-Courcouronnes" (Grigny Centre→
   Viry-Châtillon, troncon 31903) et "Voie via Melun / Combs-la-Ville" (Montgeron-Crosne→
   Villeneuve-Saint-Georges, troncon 31898). Null partout ailleurs (embranchements simples en
   arbre, qui n'ont besoin d'aucune explication) — à poser au cas par cas si une autre ligne
   présente un vrai maillage similaire (voir la trentaine identifiée, à auditer si besoin).

Vérifié dans le navigateur (compte de test) sur `/ligne/22` (RER D) : chaque station apparaît
exactement une fois, les 2 jonctions réelles affichent leur badge descriptif, plus aucune branche
fantôme. Vérifié aussi sur `/ligne/8` (Ligne 7, embranchement classique Maison Blanche) : aucune
régression. `php bin/phpunit` (137) et `npx jest` (51) : tout passe.
