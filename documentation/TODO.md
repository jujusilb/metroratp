# À faire / pistes en attente

Historique complet (tâches achevées, avec leur contexte technique détaillé) migré vers `/tache`
(base de données, réservé `ROLE_ADMIN`) le 2026-08-16 — voir `documentation/commande.md` pour le
détail de cette migration. Ce fichier ne garde désormais que ce qui reste réellement à faire.

## Style physique des Acces (demandé le 2026-08-15, pas commencé)

Demande utilisateur : pour chaque Acces, indiquer s'il y a un escalator, un édicule Guimard, un
mât, ou un autre style d'entrée reconnaissable. Jamais commencé — probable faible rendement sur
Wikidata (constaté en marge du travail sur `StyleStation` : un seul édicule d'entrée dans tout
Wikidata porte à la fois `P84`=Guimard et `P31`=entrée de station). À vérifier plus sérieusement
avant de conclure que la piste n'est pas exploitable.

## Pistes de données IDFM non encore exploitées

* `emplacement-des-gares-idf-data-generalisee.csv` (999 lignes) : une ligne par gare avec
  `id_ref_ZdC`/`id_ref_ZdA` (mêmes clés que `relations.csv`), coordonnées (Geo Point + x/y
  Lambert93), `exploitant`, et des indicateurs de mode (train/rer/metro/tramway/val) + terminus
  par mode (`tertrain`/`terrer`/etc.). Source alternative de géoloc/exploitant par station à
  croiser avec l'existant — vérifier si ça comble des trous plutôt que faire doublon.
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

## Écarts arrêts référentiel/OpenStreetMap — piste non commencée

`ecarts-arrets-referentiel-et-openstreetmap.csv` : recoupement par arrêt (ArT) entre le
référentiel IDFM et OpenStreetMap (distance entre les deux positions + équipements OSM :
wheelchair, bench, bin, lit, shelter, tactile_paving). Colonnes d'équipements potentiellement
utiles (banc, poubelle, éclairage, abri, bande tactile) qui n'existent nulle part ailleurs dans le
projet. À croiser avec `arrets-transporteur.csv` (même niveau ArT) et `relations.csv` (chaîne vers
ZdC/Station) pour maximiser ce qu'on peut en tirer avant de décider quoi importer. Pas commencé.

## Arrêt Transporteur (ArT) — piste non commencée

Descendre au niveau le plus fin de la hiérarchie IDFM (ZdC → ZdA → ArR → **ArT**, un ArT = un
arrêt physique d'un opérateur donné) plutôt que de rester au niveau Station (ZdC) comme
actuellement. Fichiers à croiser pour en tirer le maximum : `arrets-transporteur.csv` (référentiel
ArT : nom, coordonnées, ville, accessibilité/signalétique par arrêt physique),
`ecarts-arrets-referentiel-et-openstreetmap.csv` (recoupement avec OSM, wheelchair/bench/bin/lit/
shelter/tactile_paving par ArT), `sdap-arrets-associes.csv` (équipements SDAP détaillés par
arrêt/ligne), et `relations.csv` (chaîne complète PdE→ZdC→ZdA→ArR→ArT avec géométrie à chaque
niveau, pour rattacher proprement un ArT à sa Station). Pas commencé.

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

**RER D, zone Évry/Corbeil/Juvisy (découvert le 2026-08-09)** : pas un simple aller-retour mais
un vrai maillage local avec au moins 2 cycles indépendants (Villeneuve-Saint-Georges ↔
Corbeil-Essonnes via Juvisy *ou* via Melun ; Corbeil-Essonnes ↔ Viry-Châtillon via
Évry-Val-de-Seine *ou* via Grigny-Centre). Même limite du modèle Direction/tronçon (pense un
arbre, pas un graphe avec cycles) que pour la RER C. Le reste de la ligne D (tronc Creil ↔
Villeneuve-Saint-Georges, branche Malesherbes) est un arbre normal et a été construit.

## Stations Metro/Tramway/RER dupliquées (découvert le 2026-08-09)

En creusant pourquoi les correspondances inter-modes rataient les grosses gares (Gare du Nord,
La Défense...), découverte d'un problème bien plus large : **~486 stations Métro/Tramway/RER
"originales"** (créées à la main tôt dans le projet, avant `app:importer-reseau-complet`)
**ont un doublon exact** créé plus tard par l'import du réseau complet — même lieu réel, même
ZdCId officiel une fois résolu, mais deux lignes `Station` distinctes (l'originale avec
`code_externe` NULL, la nouvelle avec le bon ZdCId). Quasiment tout le réseau métro/RER/tram
d'origine est concerné, pas seulement les gros hubs.

Contournement en place : `ConstruireCorrespondancesInterModesCommand` regroupe par **label** de
station plutôt que par id, donc les correspondances fonctionnent malgré les doublons. Mais le
problème de fond reste : toute future fonctionnalité qui regroupe par `Station` (pas par label)
tombera dans le même piège.

Vraie correction : fusionner ces ~486 paires de `Station` dupliquées (réassigner toutes les FK —
`Desserte`, `TronconDesserte`, `Direction`, `Correspondance`, `Sortie` — de l'originale vers la
nouvelle avant de supprimer l'originale, ou l'inverse). Opération delicate et invasive (touche
quasiment toutes les entités), volontairement **pas faite dans cette session** : reperée via
`documentation/scripts/backfill_code_externe_stations_originales.py` (génère
`documentation/scripts/donnees-extraites/backfill_code_externe.sql`, qui liste aussi les vraies
paires en doublon dans sa sortie console). 37 stations sans doublon ont été reliées à leur ZdC au
passage (aucun risque, `code_externe` libre) ; 10 restent ambiguës (nom trop générique, plusieurs
ZdC candidats) et 16 sans correspondance ZdC trouvée — à revoir manuellement.

## Autres pistes notées en cours de route

- Lisibilité des badges de ligne (demandé le 2026-08-17) : actuellement (`.ligne-badge`/
  `.ligne-badge-sm`, `assets/styles/app.scss`, utilisé dans 18 templates) le label de la ligne est
  écrit EN texte À L'INTÉRIEUR du rond colorisé (`background-color: {{ ligne.couleur|default(...)
  }}`), donc illisible si la couleur est claire/proche du blanc, et une couleur grise par défaut
  est utilisée quand `couleur` est NULL. Cible demandée : le rond coloré reste vierge de tout texte
  (juste la couleur, absent s'il n'y a pas de `couleur` connue) ; le nom de la ligne s'affiche
  toujours À CÔTÉ, en texte noir sur fond blanc (jamais dans le rond) ; le mode de transport
  (`Ligne.typeTransport`, ex: Bus/Tram/RER/Métro) doit aussi être visible à côté. Implique de
  revoir les 18 templates utilisant ce badge, pas juste le CSS.
- Tracés de bus vs vol d'oiseau (signalé le 2026-08-17, capture d'écran `/trajet` à l'appui) :
  `assets/js/trajet-carte.js` essaie déjà d'extraire, pour chaque tronçon affiché, la portion du
  vrai tracé GPS de la `Ligne` (`Ligne::trace`, importé par `app:importer-traces-lignes` depuis
  `traces-des-lignes-de-transport-en-commun-idfm.csv`, couvre tous les modes dont le bus) entre
  les deux arrêts, avec repli sur une ligne droite si aucune composante du tracé n'est jugée assez
  proche des deux points. Ce repli se déclenche visiblement pour au moins une ligne de bus (trait
  bleu rectiligne traversant plusieurs pâtés de maisons au lieu de suivre les rues). À vérifier :
  le tracé de cette ligne est-il manquant/mal rattaché en base, ou est-ce l'heuristique de
  proximité (la plus proche à la fois de A et B) qui échoue sur ce cas précis ? S'assurer que
  chaque tronçon de bus est bien synchronisé avec son vrai tracé GPS, pas juste une approximation
  point à point.
- Quais décalés (ex: Liège sur la ligne 13) : le modèle actuel suppose une distance symétrique
  par tronçon, ne capture pas les cas où la distance de marche diffère selon le sens réel.
- Lignes de bus : reste du réseau non traité. Fait le 2026-08-11 pour les lignes numérotées 20 à
  299 (`app:construire-topologie-bus`/`app:construire-topologie-bus-autres-operateurs`), sauf les
  lignes non-RATP de la plage 101-299 (ATM Croix du Sud 179/189-191/194-195/289-290, Keolis Grand
  Paris Vallée de la Marne 206-207/209/211-213/220, Keolis Argenteuil 262, Keolis Ouest
  Val-de-Marne 282 — pas encore demandé). Le reste du réseau bus (~1300 lignes hors 20-299) n'a
  toujours aucun tronçon construit — ampleur trop importante pour un seul passage, mais la méthode
  (extraction GTFS + réduction des raccourcis, voir
  `documentation/scripts/extraire_troncons_bus*.php`) est directement réutilisable.

## Lignes Transilien V/P/R (pas encore dans la base)

En ajoutant le matériel roulant RER (2026-08-09), plusieurs séries sont notées "exploitation
commune" avec des lignes Transilien pas encore modélisées : Z 5600/8800/20500/20900 (RER C) avec
la **ligne V**, Z 57000/57400 (RER D) avec la **ligne R**, Z 50000 (RER E) avec la **ligne P**. Si
on veut représenter fidèlement ce lien un jour, il faudra créer ces 3 lignes Transilien (SNCF).

## Ligne.codeExterne incohérent pour le métro — vrai nettoyage pas fait

Nos Ligne de métro "doublons" (créées par `app:importer-reseau-complet`, même phénomène que les
Stations dupliquées ci-dessus, mais sur `Ligne`) ont un `codeExterne` qui ne correspond plus au
GTFS actuel : ex. notre ligne "7" (id avec codeExterne) pointe vers `C00312`, qui est en réalité
dans le GTFS courant une ligne de BUS renommée "6402 (ex 7)" — vraisemblablement un résidu d'un
très ancien import, jamais nettoyé. Contourné ponctuellement (rattachement par label) dans
`app:construire-positions-rame` et `app:importer-traces-lignes`, mais pas corrigé à la source :
toute future fonctionnalité qui matche une Ligne de métro par `codeExterne` tombera dans le même
piège. `referentiel-des-lignes.csv` (pas encore utilisé) est une piste pour un vrai nettoyage.
