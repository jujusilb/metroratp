# À faire / pistes en attente

## Lignes à embranchements complexes (RER C notamment)

Le modèle actuel (`Direction` = un par terminus réel, `numero` = position du tronçon,
réutilisée par toutes les directions qui le traversent) fonctionne pour un simple Y
(vérifié sur les trams T4 et T8 : tronc commun + 2 branches). Mais il n'a pas encore été
mis à l'épreuve d'un **arbre à plusieurs niveaux** (branches qui se subdivisent elles-mêmes,
des deux côtés), typiquement la **RER C** qui a environ 8 branches côté sud et plusieurs
côté nord/ouest.

Le mécanisme lui-même devrait tenir (voir échange du 2026-08-08) : `Direction` par terminus
quel que soit le nombre de bifurcations en chemin, `numero` assigné une fois par tronçon
via un parcours systématique de l'arbre (ex: BFS depuis Gare d'Austerlitz), réutilisé par
toutes les directions qui empruntent ce tronçon. La vraie difficulté est la **reconnaissance**
(cartographier correctement toutes les bifurcations et tous les terminus réels avant de coder),
pas le modèle de données.

À traiter quand on s'attaquera à la RER C (ou toute autre ligne à embranchements profonds).

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

- Coordonnées du plan schématique (`Station.schemaX/Y`) manquantes pour les stations créées
  après l'import RER/reseau complet (~14000 stations) — la source `schema_gares-gf` n'a pas
  été retrouvée localement.
- ~~Église d'Auteuil (ligne 10) : sans mission dédiée~~ — vérifié le 2026-08-09 : `Mission`
  n'est utilisée nulle part dans le site (ni le calcul de trajet ni l'affichage des lignes),
  donc aucun effet fonctionnel. En revanche, corrigé un vrai bug trouvé au passage : le tronçon
  Michel-Ange—Auteuil ↔ Église d'Auteuil était bidirectionnel alors que cette antenne n'est
  desservie que dans un seul sens (Michel-Ange—Auteuil → Église d'Auteuil, confirmé par le plan
  RATP officiel et Wikipédia — quai unique, voie nord de la boucle d'Auteuil). Audité tout le
  reste du réseau métro/tram pour d'autres antennes mal modélisées en bidirectionnel : aucune
  autre trouvée (la boucle 7bis Botzaris/Danube/Place des Fêtes/Pré-Saint-Gervais était déjà
  correcte en sens unique).
- Quais décalés (ex: Liège sur la ligne 13) : le modèle actuel suppose une distance symétrique
  par tronçon, ne capture pas les cas où la distance de marche diffère selon le sens réel.
- ~~Lignes de bus (~1400...) : aucun tronçon construit~~ — **fait le 2026-08-11 pour les lignes
  numérotées 20 à 299** : 20-100 toutes compagnies confondues (RATP + Keolis Roissy/Argenteuil +
  Transdev Boucle des Lys/Vallée du Loing/Nord Seine-Saint-Denis/Côteaux de la Marne + Keolis Nord
  Val d'Oise, voir `app:construire-topologie-bus` et `app:construire-topologie-bus-autres-operateurs`),
  101-299 uniquement RATP/filiales "RATP Cap ..." (voir `app:construire-topologie-bus`, même
  commande, map étendue). **Pas encore fait dans 101-299** : les lignes non-RATP de cette plage
  (ATM Croix du Sud 179/189-191/194-195/289-290, Keolis Grand Paris Vallée de la Marne 206-207/
  209/211-213/220, Keolis Argenteuil 262, Keolis Ouest Val-de-Marne 282 — pas encore demandé). Le
  reste du réseau bus (~1300 lignes hors 20-299) n'a toujours aucun tronçon construit — ampleur
  trop importante pour un seul passage, mais la méthode (extraction GTFS + réduction des
  raccourcis, voir `documentation/scripts/extraire_troncons_bus*.php`) est directement
  réutilisable pour étendre à d'autres plages de numéros.
- ~~Aucune correspondance bus<->bus / bus<->metro / bus<->rer / bus<->tram (seulement metro/tram/RER
  entre eux, `ConstruireCorrespondancesInterModesCommand`, limité aux modes lourds pour éviter
  l'explosion combinatoire d'une approche "toutes les paires à un même arrêt" sur ~1400 lignes de
  bus)~~ — **fait le 2026-08-11** grâce à `transfers.txt` (GTFS IDFM), qui documente déjà les vraies
  correspondances piétonnes officielles entre deux Stations différentes (pas besoin de générer
  toutes les combinaisons nous-mêmes, la source fait le tri) : **106 757 correspondances créées**
  (`app:construire-correspondances-bus`, voir `extraire_correspondances_inter_zdc.php`), dont
  102 749 bus↔bus, ~3200 bus↔tram/train, 227 RER↔bus, 119 métro↔bus. Testé de bout en bout
  (RER A à Châtelet → correspondance → bus 21 → 15 arrêts). Au passage, 9 correspondances
  métro/tram/RER existantes (distance NULL, estimation par défaut) ont été affinées avec un vrai
  temps de marche GTFS (`app:affiner-distances-correspondances`) — la plupart des correspondances
  existantes avaient déjà une distance vérifiée manuellement, non écrasée.

## Carte du calculateur de trajet (fait le 2026-08-12)

~~La carte du calculateur de trajet n'utilisait que `Station.schemaX/Y` (plan schématique officiel,
METRO seulement, ~300/315 stations) — RER/tram/bus n'apparaissaient jamais, meme quand le trajet
les traversait reellement.~~ **Remplace par de vraies coordonnees geographiques** (`Station.latitude/
longitude`, `app:importer-coordonnees-geographiques`, depuis `zdc_coordonnees.csv` extrait du GTFS) :
couvre tous les modes sur toute l'Ile-de-France (13696 Stations via codeExterne + 371 de plus par
repli sur le nom exact pour les Stations "originales" sans codeExterne). Rendu passe d'un SVG
schematique fait main a une vraie carte Leaflet/OpenStreetMap (`assets/js/trajet-carte.js`),
affichee dans une modale plein ecran (bouton "Carte").

**Limite residuelle connue** : le repli par nom exact ne resout que ~70% des ~534 Stations
"originales" sans codeExterne (le probleme de doublons documente plus haut) — les noms dont la
graphie differe de celle du referentiel IDFM restent sans coordonnees (ex: "Châtelet" seul, la
jumelle ZdC-liee s'appelant "Châtelet - Les Halles" ou "Châtelet (Paris 4e)" ; "Reuilly — Diderot"
avec son tiret cadratin). Pour ces stations precises, le trace mis en evidence sur la carte peut
etre incomplet ou vide bien que l'itineraire textuel reste correct. Un rapprochement plus tolerant
(normalisation + repli par inclusion de mots, comme `app:importer-coordonnees-schema`) resoudrait
une partie du reste, mais la vraie correction reste la fusion des Stations dupliquees (voir plus
haut). Verifie une fausse correspondance ("Saint-Paul" du Marais rapproche par erreur d'un arret de
bus rural homonyme) : corrigee via une petite liste d'exclusion manuelle dans la commande
(`EXCLUSIONS_CONNUES`) — a completer si un nouveau cas est repere.

## Performance de TrajetFinder (decouvert le 2026-08-12, pas corrige)

`TrajetFinder::construireGraphe()` reconstruit l'integralite du graphe (tous les Troncon ET toutes
les Correspondance) via l'ORM a **chaque** calcul de trajet, avec des fetch-joins tres larges
(`TronconRepository`/`CorrespondanceRepository::findAllWithDetails()` : missions, directions,
etc.). Devenu tres lent (~12s par requete, ~193 000 entites Doctrine hydratees) depuis que la table
`correspondance` est passee de ~31 000 a ~155 000+ lignes (correspondances bus, voir plus haut) et
que `troncon` compte desormais ~7241 lignes (bus compris). Le meme probleme a ete corrige pour le
fond de carte (nouvelle methode SQL brute `TronconRepository::tronconsPourCarte()`, quasi
instantanee) mais **pas pour le calcul d'itineraire lui-meme**, qui reste lent en prod.

Piste de correction (pas implementee) : meme principe - construire le graphe Dijkstra (juste les
ids et les poids) via des requetes SQL legeres plutot que l'ORM, puis ne recharger via Doctrine que
les quelques entites necessaires aux `Etape` du chemin **trouve** (motif "requete legere + recharge
par ids" deja utilise ailleurs dans le projet pour la pagination).

## Lignes Transilien V/P/R (pas encore dans la base)

En ajoutant le matériel roulant RER (2026-08-09), plusieurs séries sont notées "exploitation
commune" avec des lignes Transilien pas encore modélisées : Z 5600/8800/20500/20900 (RER C) avec
la **ligne V**, Z 57000/57400 (RER D) avec la **ligne R**, Z 50000 (RER E) avec la **ligne P**. Si
on veut représenter fidèlement ce lien un jour, il faudra créer ces 3 lignes Transilien (SNCF).
