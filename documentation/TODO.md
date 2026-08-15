# À faire / pistes en attente

## Piège de cache navigateur en dev local (resolu le 2026-08-14, a retenir)

`symfony server:start` (serveur PHP integre) ne renvoie aucun header `Cache-Control`/`ETag` sur
les fichiers statiques (`public/build/*.js/css`). Sans hash de version dans le nom de fichier, le
navigateur applique un cache heuristique base sur `Last-Modified` et peut servir un `app.js` vieux
de plusieurs heures/jours malgre des dizaines de `npx encore dev` successifs - **sans aucune
erreur visible**, juste un comportement JS qui semble "ne rien faire". Corrige une fois pour
toutes via `webpack.config.js` (`.enableVersioning(true)`, meme en dev) : chaque build produit un
nom de fichier different (`app.<hash>.js`), donc une URL differente = jamais de cache perime. Si
un changement JS semble ne jamais prendre effet localement malgre un rebuild reussi, verifier ce
point AVANT de chercher un bug de logique (`fetch(url, {cache:'no-store'})` permet de comparer le
contenu reellement charge par le navigateur vs le fichier source sur disque).

## Plans de secteur (fait le 2026-08-14)

Nouvelle entité `Plan` (dataset IDFM `plans-de-secteur`, 73 secteurs) + `Station::plan` (FK
many-to-one, une Station sur au plus un Plan). CRUD complet (`/plan`), lien affiché sur la fiche
Station, champ éditable dans le formulaire Station.

Les 72/73 PDF (le plan 50 "En cours de réalisation" est indisponible côté IDFM) ont été
téléchargés dans `documentation/IDFM-gtfs/plan secteur/` (337 Mo, non commit, gitignore) pour
référence locale, mais **le site ne les héberge pas** : `Plan::urlPdf` pointe vers le PDF officiel
IDFM (décision utilisateur du 2026-08-14 — éviter d'alourdir l'hébergement mutualisé Hostinger).

Assignation automatique de `Station::plan` (`app:importer-plans-secteur`) : le département de la
Station est déduit de `Station::ville` (règle spéciale "Paris Ne" → 75, sinon correspondance
exacte dans `communes_departements.csv`, extrait de `communes-par-contrat.csv` via
`documentation/scripts/extraire_communes_departements.php`), puis le Plan n'est assigné que si ce
département est couvert par **un seul** Plan. **Constat après avoir chargé les vraies données** :
seul le département 75 (Paris) est couvert par un seul Plan (le n°3) — tous les départements de
grande couronne sont scindés en plusieurs Plan (le 77 en compte 24). Résultat : 878 stations
parisiennes assignées automatiquement, le reste du réseau (~13000 stations) doit être assigné à la
main via le formulaire Station — c'est le comportement voulu (repli automatique + assignation
manuelle), pas un bug.

**Découverte en vérifiant le déploiement** : le `--exclude='documentation'` du rsync de
déploiement (`.github/workflows/tests.yml`) semblait impliquer qu'il fallait uploader les CSV
dérivés (`documentation/scripts/donnees-extraites/`) à la main par SSH après coup — en fait ce
n'est pas nécessaire : `documentation/IDFM-gtfs/` (le seul contenu vraiment volumineux) est
gitignore et n'existe donc jamais dans le checkout du runner, donc rien de gros n'est jamais
exclu en pratique ; tout le reste de `documentation/` (docs + CSV dérivés commit) part bien avec
le rsync malgré l'exclude (vérifié le 2026-08-14 : `plans-de-secteur.csv` et
`communes_departements.csv` étaient déjà présents et à jour sur le serveur juste après le
déploiement, hash identique au fichier commit une fois les fins de ligne normalisées).

## PDF affichés directement sur le site (fait le 2026-08-15, branche feature/plans-regionaux)

Même traitement que sur `plan/show.html.twig` (main) : `templates/tools/visionneuse_pdf.html.twig`
appliqué à `plan_region/show.html.twig` (PDF visible directement sur la page, plus de lien qui
ouvre juste un nouvel onglet).

## Plans régionaux (fait le 2026-08-15, branche feature/plans-regionaux)

`PlanRegion` (19 grandes cartes d'ensemble du réseau : Métro, RER, réseau de Nuit, plans
PMR/facile à lire..., dataset IDFM "plans-region"). Même traitement que `Plan` (secteurs) : PDF
jamais auto-hébergé, lien vers l'officiel IDFM. CRUD complet (`/plan-region`). Ajouté à l'onglet
"Carte des secteurs" existant (`/carte`) sous forme de second `<optgroup>` dans le même sélecteur
plutôt qu'un nouvel onglet séparé — réutilise tel quel le mécanisme modal `<object>` déjà en place.

## Accessibilité PMR par gare (fait le 2026-08-15)

`Station::accessibilitePmr`/`accessibilitePmrCommentaire` depuis `accessibilite-en-gare.csv`
(dataset IDFM, 459 gares - train/RER/métro principalement). Le CSV source ne donne aucune clé
directe vers le référentiel ZdC utilisé partout ailleurs (`stop_point_id` façon
`stop_point:IDFM:monomodalStopPlace:47915`) : résolu via `stops.txt` (`parent_station`) dans
`documentation/scripts/extraire_accessibilite_gares.php` (455/459 résolues, 4 hors du snapshot
GTFS téléchargé). Affiché sur la fiche Station uniquement quand renseigné (grain "gare", la
grande majorité des Station bus n'auront jamais cette donnée - fidèle à la source, pas un import
manquant).

## Mini-carte des accès / "plan de quartier" (fait le 2026-08-14)

L'utilisateur voulait l'équivalent du plan de quartier affiché sur les quais RATP (petite carte
locale montrant où mène chaque sortie numérotée). Aucun dataset ouvert IDFM ne fournit ce visuel
(vérifié par recherche sur data.iledefrance-mobilites.fr : rien entre `plans-de-secteur` — trop
zoomé — et `acces` — pas de carte, juste des coordonnées). Reconstruit "maison" plutôt que
d'essayer de sourcer un visuel propriétaire RATP :

- `Acces::latitude`/`longitude` (nouveau, depuis `stop_lat`/`stop_lon` de stops.txt GTFS,
  `location_type=2` — jamais importé avant malgré la présence du champ `AccGeopoint` dans
  `acces.csv` source).
- `carte-acces.js` : petite carte Leaflet par Station (fond CARTO Positron plutôt que les tuiles
  OSM standard — plus proche visuellement d'un plan, façades de bâtiments visibles), un bandeau
  bleu "Sortie N — libellé" par Acces connu (style RATP), sur la fiche Station.

Limite assumée : pas de bâtiments/commerces nommés (Théâtre, École, Église...) sur la mini-carte,
faute de dataset POI dans le projet — seuls le fond de carte OSM/CARTO et les accès sont affichés.

## Pôles d'échange (fait le 2026-08-14)

Nouvelle entité `PoleEchange` (dataset IDFM `poles-d-echange`, seulement 10 hubs officiels :
grandes gares/aéroports) + `Station::poleEchange` (FK many-to-one, une Station sur au plus un
Pole). CRUD complet (`/pole-echange`), lien affiché sur la fiche Station, champ éditable dans son
formulaire.

Le dataset source ne contient qu'un id et un nom de pôle, **aucune clé de rattachement** vers les
Station (pas de ZdCId). Un matching flou par nom a été explicitement écarté après l'avoir testé
sur les vraies données : `LIKE '%Roissy%'` ou `LIKE '%Charles de Gaulle%'` remontent des dizaines
d'arrêts sans rapport partout en Île-de-France (ex: "Charles de Gaulle" est aussi un nom de rue
très commun). À la place, `app:importer-poles-echange` utilise une liste **vérifiée à la main**
(constante PHP `STATIONS_PAR_POLE`, 32 couples label+ville) construite en interrogeant chaque
candidat individuellement avant de l'inclure — même piège que `schema_gares-gf`/traces de lignes
plus tôt dans le projet (voir plus bas) : ne jamais faire confiance à un matching par nom seul sans
vérifier les faux positifs sur le jeu de données réel.

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

- ~~Coordonnées du plan schématique (`Station.schemaX/Y`) manquantes pour les stations créées
  après l'import RER/reseau complet (~14000 stations) — la source `schema_gares-gf` n'a pas
  été retrouvée localement.~~ — **fait le 2026-08-14** : source retrouvée (téléchargée par
  l'utilisateur), `app:importer-coordonnees-schema` étendue à tous les modes ferrés (métro/RER/
  tram/train, plus seulement métro) avec un garde-fou (Stations candidates restreintes a celles
  desservies par un mode ferre lourd - sans ca, le rapprochement par nom matchait aussi des
  milliers d'arrets de bus). 1037 Stations positionnées (contre ~300 avant). **A noter** :
  `schemaX/Y` n'est plus utilisé par aucune fonctionnalité visible depuis que la carte du trajet
  utilise `latitude`/`longitude` (vraies coordonnées geographiques, voir plus haut) — cette donnée
  est complète mais dormante, utile seulement si une bascule "plan schematique officiel" est
  ajoutée un jour.
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

## Conseils de position dans la rame (fait le 2026-08-13)

Nouvelle table `PositionRame` (dataset IDFM "positionnement-dans-la-rame") : pour une Ligne et une
Station de depart, ou se placer dans la rame pour arriver au plus pres d'une sortie ou d'une
correspondance. Affiche sur la page de chaque Station. `app:construire-positions-rame` (4671
lignes, 18 lignes couvertes : metro 1-14+3B+7B, RER A/B - le dataset source ne couvre pas plus).

**Bug decouvert au passage : `Ligne.codeExterne` incoherent pour le metro.** Nos Ligne de metro
"doublons" (creees par `app:importer-reseau-complet`, voir plus bas "Stations dupliquees" - le
meme phenomene existe aussi sur `Ligne`, pas seulement `Station`) ont un `codeExterne` qui ne
correspond plus au GTFS actuel : ex. notre ligne "7" (id avec codeExterne) pointe vers `C00312`,
qui est en realite dans le GTFS courant une ligne de BUS renommee "6402 (ex 7)" - vraisemblablement
un residu d'un tres ancien import, jamais nettoye. Contourne pour `app:construire-positions-rame`
en rattachant par label plutot que par codeExterne (sans risque de collision : seulement 18 lignes
metro/RER couvertes par ce dataset, pas de bus). **Pas corrige a la source** : toute future
fonctionnalite qui matche une Ligne de metro par `codeExterne` (a la maniere des lignes de bus)
tombera dans le meme piege - verifier `referentiel-des-lignes.csv` (pas encore utilise) comme
piste pour un vrai nettoyage.

**Meme famille que le probleme de Station dupliquee, mais impactant cette fois l'affichage
directement** (pas seulement une donnee derivee comme les coordonnees de la carte) : Accès/Sorties
et PositionRame se rattachaient initialement a la Station ZdC-liee (jamais consultee en pratique)
plutot qu'a la Station "originale" (celle affichee par `/station/{id}`). Corrige par
`StationRepository::trouverIdCanoniqueParZdc()`, reutilisable pour toute future donnee importee
par ZdC qui doit s'afficher sur la bonne page.

## Performance de TrajetFinder (decouvert le 2026-08-12, corrige le 2026-08-13)

~~`TrajetFinder::construireGraphe()` reconstruit l'integralite du graphe (tous les Troncon ET toutes
les Correspondance) via l'ORM a **chaque** calcul de trajet, avec des fetch-joins tres larges
(`TronconRepository`/`CorrespondanceRepository::findAllWithDetails()` : missions, directions,
etc.). Devenu tres lent (~12s par requete, ~193 000 entites Doctrine hydratees) depuis que la table
`correspondance` est passee de ~31 000 a ~155 000+ lignes.~~ **Corrige** : `construireGraphe()`
reecrit en SQL brut (ids + poids seulement, aucune entite ORM chargee pour l'ensemble du reseau),
seules les quelques dizaines de `Desserte` du chemin **trouve** sont rechargees via l'ORM a la fin
(meme motif "requete legere + recharge par ids" que pour le fond de carte). Passe de ~12-14s
(~193 000 entites) a ~2,5s (~30 entites, 58 Mo de pic memoire). La correction est devenue urgente
le jour meme : l'ajout de `Ligne::trace` (potentiellement volumineux) a fait passer le probleme de
"lent" a un **"Allowed memory size exhausted" (erreur 500) pur et simple** des qu'une Ligne
concernee avait un gros trace, l'ancien code hydratant une Ligne complete (trace compris) par
Desserte du reseau entier.

Reste ~1,7s de temps SQL par requete (deux grosses requetes brutes scannant tout `troncon_desserte`
et toute `correspondance` a chaque fois) : correct pour un usage normal, mais une vraie optimisation
future consisterait a limiter/indexer davantage, ou a mettre en cache le graphe entre deux requetes
(non fait, pas juge necessaire vu le gain deja obtenu).

## Trace geometrique reel des Lignes (fait le 2026-08-13)

`Ligne::trace` (JSON, liste de composantes/branches, chacune une liste de points [lon,lat]),
depuis le dataset IDFM "traces-des-lignes-de-transport-en-commun-idfm" (tous modes : 1882 bus, 24
RER/Transilien, 16 metro, 17 tram, 2 funiculaire/telepherique). Simplifie (Douglas-Peucker,
tolerance ~3m) et arrondi (5 decimales, ~1,1m) a l'extraction : 76 Mo -> 22,6 Mo, forme visuelle
inchangee. `app:importer-traces-lignes` (meme rattachement par label pour le metro que
`app:construire-positions-rame`, `Ligne.codeExterne` etant incoherent pour ce mode - voir plus
haut) : 1445/1936 lignes rattachees.

La carte du calculateur de trajet utilise ce trace reel pour dessiner le trajet en suivant les
rues/rails plutot qu'une ligne droite entre deux stations consecutives (`assets/js/trajet-carte.js`,
`extraireTraceEntreDeuxPoints` : projette les deux stations sur chaque composante du trace de la
Ligne empruntee, choisit la plus proche des deux, decoupe la portion entre les deux projections).
Verifie sur un vrai cas (Bastille -> Gare de Lyon, ligne 1) : 13 points de trace reel utilises
plutot qu'un simple segment. Le fond de reseau (toutes les autres lignes, attenuees) reste en
lignes droites - seul le trajet mis en evidence beneficie du trace reel, pour ne pas alourdir la
carte avec des traces de tout le reseau a chaque requete (seules les 1-3 lignes du trajet trouve
sont transmises, voir `TrajetController::construireTracesLignesPourAffichage()`).

**A ete l'occasion de decouvrir et corriger un bug bien plus grave (voir section Performance de
TrajetFinder plus bas)** : l'ancien `TrajetFinder::construireGraphe()` chargeait via l'ORM
l'integralite du reseau (Troncon+Correspondance+Desserte+**Ligne**) a chaque calcul de trajet ; en
ajoutant `Ligne::trace` (potentiellement volumineux), ce chargement complet du reseau a fait passer
le calculateur de trajet d'"lent" (~12s) a **totalement casse (erreur 500, memoire epuisee)**.

## Lignes Transilien V/P/R (pas encore dans la base)

En ajoutant le matériel roulant RER (2026-08-09), plusieurs séries sont notées "exploitation
commune" avec des lignes Transilien pas encore modélisées : Z 5600/8800/20500/20900 (RER C) avec
la **ligne V**, Z 57000/57400 (RER D) avec la **ligne R**, Z 50000 (RER E) avec la **ligne P**. Si
on veut représenter fidèlement ce lien un jour, il faudra créer ces 3 lignes Transilien (SNCF).
