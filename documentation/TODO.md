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

## Autres pistes notées en cours de route

- Coordonnées du plan schématique (`Station.schemaX/Y`) manquantes pour les stations créées
  après l'import RER/reseau complet (~14000 stations) — la source `schema_gares-gf` n'a pas
  été retrouvée localement.
- Église d'Auteuil (ligne 10) : connectée topologiquement mais sans mission dédiée (desserte
  spéciale à service limité, non modélisée).
- Quais décalés (ex: Liège sur la ligne 13) : le modèle actuel suppose une distance symétrique
  par tronçon, ne capture pas les cas où la distance de marche diffère selon le sens réel.
- Lignes de bus (~1400, dont ~210 avec plan officiel RATP déjà disponible dans
  `documentation/PLAN/PDF/BUS/`) : stations/lignes/dessertes peuplées, mais aucun tronçon/
  mission construit — ampleur trop importante pour un seul passage.

## Matériel roulant RER (table `materiel` + `materiel_ligne`)

Séries à ajouter, avec effectif et date de référence (relevé manuel de l'utilisateur) :

- **RER A** : MI 09 (140 éléments depuis avril 2017), MI 2N (42 éléments au 26/02/2021).
- **RER B** : MI 79 (116 éléments au 09/07/2020), MI 84 (42 éléments au 05/03/2025).
- **RER C** : Z 5600 (30 éléments au 13/12/2025, exploitation commune avec la ligne V),
  Z 8800 (35 éléments au 06/03/2025, exploitation commune avec la ligne V),
  Z 20500 (67 éléments au 30/06/2026, exploitation commune avec la ligne V),
  Z 20900 (54 éléments au 06/03/2025, exploitation commune avec la ligne V).
- **RER D** : Z 20500 (112 éléments au 13/12/2025),
  Z 57000/57400 (79 éléments au 28/06/2026, exploitation commune avec la ligne R),
  Z 58500 (34 éléments au 27/07/2026).
- **RER E** : Z 22500 (23 éléments au 08/07/2026),
  Z 50000 (62 éléments au 23/04/2026, exploitation commune avec la ligne P),
  Z 58000 (78 éléments au 04/07/2026).

Note : le RER A a déjà MS61/MI84/MI09 et le RER B déjà MS61/MI79/RERng renseignés par
`ImporterLignesRerCommand` — vérifier les doublons/écarts d'effectif avant d'ajouter MI09/MI2N/
MI79/MI84 ci-dessus (l'utilisateur a peut-être des chiffres plus à jour que l'import initial).
"Ligne V"/"Ligne P"/"Ligne R" = lignes Transilien (SNCF), pas encore dans la base — à créer si
on veut représenter fidèlement le lien "exploitation commune".
