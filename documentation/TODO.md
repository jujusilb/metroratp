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
