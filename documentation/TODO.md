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
- Lignes de bus (~1400, dont ~210 avec plan officiel RATP déjà disponible dans
  `documentation/PLAN/PDF/BUS/`) : stations/lignes/dessertes peuplées, mais aucun tronçon/
  mission construit — ampleur trop importante pour un seul passage.

## Lignes Transilien V/P/R (pas encore dans la base)

En ajoutant le matériel roulant RER (2026-08-09), plusieurs séries sont notées "exploitation
commune" avec des lignes Transilien pas encore modélisées : Z 5600/8800/20500/20900 (RER C) avec
la **ligne V**, Z 57000/57400 (RER D) avec la **ligne R**, Z 50000 (RER E) avec la **ligne P**. Si
on veut représenter fidèlement ce lien un jour, il faudra créer ces 3 lignes Transilien (SNCF).
