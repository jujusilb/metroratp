# À faire / pistes en attente

Historique complet (tâches achevées, avec leur contexte technique détaillé) migré vers `/tache`
(base de données, réservé `ROLE_ADMIN`) le 2026-08-16 — voir `documentation/commande.md` pour le
détail de cette migration. Ce fichier ne garde désormais que ce qui reste réellement à faire.

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
  precise. Note : le dataset source contient parfois plusieurs lignes identiques pour une même
  destination (déjà visible auparavant sur la fiche Station) — non dédupliqué, hors périmètre de
  cette demande.
- Page `/ligne/{id}` (signalé le 2026-08-22) — deux soucis distincts sur la liste des stations
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
  tout le réseau "Pays Briard" qui manque, juste cette ligne précise. Cause exacte non confirmée :
  soit aucun trip programmé pour cette ligne dans l'instantané GTFS utilisé (service scolaire/
  saisonnier ?), soit un autre écart référentiel/GTFS. Les fichiers GTFS bruts (`trips.txt`/
  `stop_times.txt`/`routes.txt`) ne sont plus présents en local (volumineux, jamais commités) —
  vérification définitive nécessiterait de retélécharger le flux GTFS complet IDFM et de
  régénérer `reseau_complet.csv`.
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
- ZdC absents de `zdc_coordonnees.csv` / `reseau_complet.csv` (signalé le 2026-08-22, arrêt "Les
  Tournelles" cherché sur Google Maps par l'utilisateur, semblant situé à "Hautefeuille") — vérifié :
  "Hautefeuille" n'est pas la rue parisienne du même nom mais une petite commune de Seine-et-Marne
  (code postal 77224) ; l'arrêt existe bien dans le référentiel officiel IDFM
  (`documentation/IDFM-gtfs/csv/zones-d-arrets.csv`, ZdC `67756`, réseau 512 "Brie et 2 Morin"), mais
  est **absent des deux fichiers qu'on utilise réellement** : `zdc_coordonnees.csv` (nos coordonnées
  ZdC) et `reseau_complet.csv` (notre import topologie/desserte) — donc absent de la table
  `station`. Ampleur mesurée : `zones-d-arrets.csv` référence **18003 ZdC** au total, contre
  seulement **13753 dans `zdc_coordonnees.csv`** — un écart de **~4250 ZdC (23,6%)** jamais importés
  chez nous, vraisemblablement concentré sur les petits réseaux ruraux/périphériques (2e rencontre du
  jour avec ce type de réseau, après la ligne 3139 "Pays Briard" ci-dessus — écho probable du même
  phénomène : les réseaux exploités par de petits opérateurs en zone peu dense semblent
  systématiquement sous-représentés dans les fichiers qu'on a extraits). Pas de correctif ici :
  nécessiterait de reconstruire `zdc_coordonnees.csv`/`reseau_complet.csv` à partir d'une source plus
  complète (le référentiel `zones-d-arrets.csv` lui-même a des coordonnées en Lambert-93, colonnes
  6/7, à reprojeter — pas fait dans cette session).
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
  **Reste à faire** (hors périmètre de cette demande, qui portait sur Station) : les 4 autres
  entités avec un champ `ville` en varchar (`Defibrillateur`, `EquipementArret`, `PointDeVente`,
  `Utilisateur`) n'ont pas de `villeRef` — à ajouter si besoin, même mécanique directement
  réutilisable.
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
