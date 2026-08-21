<?php

namespace App\Repository;

use App\Entity\Troncon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Troncon>
 */
class TronconRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Troncon::class);
    }

    /**
     * Pour l'index (des milliers de troncons au total) : filtre par mode (via la ligne desservie
     * par au moins une des dessertes du troncon — meme notion que Ligne::getModeFiltre()) et par
     * nom de station, a paginer avec KnpPaginatorBundle.
     *
     * Un troncon n'a pas de lien direct vers Ligne/Station : il faut passer par
     * tronconDessertes -> desserte -> (ligne | station). Utilise EXISTS plutot qu'un JOIN direct
     * pour ne jamais multiplier les lignes SQL (un troncon a typiquement 4 tronconDessertes,
     * un JOIN les ferait apparaitre 4 fois et fausserait le compte total de la pagination) — voir
     * TronconController::index() pour le second temps (rechargement avec details, seulement pour
     * les ids de la page courante, via findAllWithDetails()).
     *
     * @param string[] $modes           cles Ligne::getModeFiltre() a inclure ; [] = aucun resultat
     * @param int[]    $gestionnaireIds identifiants Gestionnaire a inclure ; [] = pas de filtre
     */
    public function creerRequeteFiltree(array $modes, ?string $recherche, array $gestionnaireIds = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.id', 'ASC')
        ;

        $conditions = [];
        if (\in_array('metro', $modes, true)) {
            $conditions[] = "tt.label = 'Métro'";
        }
        if (\in_array('tram', $modes, true)) {
            $conditions[] = "tt.label = 'Tramway'";
        }
        if (\in_array('rer', $modes, true)) {
            $conditions[] = "tt.label = 'RER'";
        }
        if (\in_array('bus_ratp', $modes, true)) {
            $conditions[] = "(tt.label = 'Bus' AND g.label = 'RATP')";
        }
        if (\in_array('bus_tiers', $modes, true)) {
            $conditions[] = "(tt.label = 'Bus' AND (g.label IS NULL OR g.label != 'RATP'))";
        }
        if (\in_array('telepherique', $modes, true)) {
            $conditions[] = "tt.label = 'Téléphérique'";
        }
        if (\in_array('funiculaire', $modes, true)) {
            $conditions[] = "tt.label = 'Funiculaire'";
        }

        if ([] === $conditions) {
            $qb->andWhere('1 = 0');
        } else {
            $qb->andWhere(sprintf(
                'EXISTS (SELECT 1 FROM App\Entity\TronconDesserte tdMode
                    JOIN tdMode.desserte dMode
                    JOIN dMode.ligne ligneMode
                    LEFT JOIN ligneMode.typeTransport tt
                    LEFT JOIN ligneMode.gestionnaire g
                    WHERE tdMode.troncon = t AND (%s))',
                implode(' OR ', $conditions),
            ));
        }

        if (null !== $recherche && '' !== trim($recherche)) {
            // "2" doit remonter aussi bien un troncon touchant une station "Gare de l'Est" que
            // ceux d'une ligne "2" (metro) ou 20-29/200-299 (bus) ; "Keolis" doit remonter les
            // troncons de ce gestionnaire : recherche sur le nom de station OU le numero/nom de
            // ligne OU le nom de gestionnaire, meme principe que DesserteRepository.
            $qb->andWhere(
                'EXISTS (SELECT 1 FROM App\Entity\TronconDesserte tdRech
                    JOIN tdRech.desserte dRech
                    JOIN dRech.station sRech
                    JOIN dRech.ligne ligneRech
                    LEFT JOIN ligneRech.gestionnaire gRech
                    WHERE tdRech.troncon = t AND (sRech.label LIKE :recherche OR ligneRech.label LIKE :recherche OR gRech.label LIKE :recherche))'
            )->setParameter('recherche', '%'.trim($recherche).'%');
        }

        if ([] !== $gestionnaireIds) {
            $qb->andWhere(
                'EXISTS (SELECT 1 FROM App\Entity\TronconDesserte tdGest
                    JOIN tdGest.desserte dGest
                    JOIN dGest.ligne ligneGest
                    WHERE tdGest.troncon = t AND ligneGest.gestionnaire IN (:gestionnaireIds))'
            )->setParameter('gestionnaireIds', $gestionnaireIds);
        }

        return $qb;
    }

    /**
     * Recharge en une requete (avec toutes les jointures d'affichage, voir findAllWithDetails())
     * les troncons de la page courante, dans l'ordre des ids donnes.
     *
     * @param int[] $ids
     *
     * @return Troncon[]
     */
    public function trouverAvecDetailsParIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $troncons = $this->createQueryBuilder('t')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->leftJoin('t.typeTroncon', 'typeTroncon')->addSelect('typeTroncon')
            ->leftJoin('t.tronconDessertes', 'td')->addSelect('td')
            ->leftJoin('td.desserte', 'd')->addSelect('d')
            ->leftJoin('d.station', 'station')->addSelect('station')
            ->leftJoin('d.ligne', 'ligne')->addSelect('ligne')
            ->leftJoin('td.typeDesserte', 'typeDesserte')->addSelect('typeDesserte')
            ->leftJoin('td.missions', 'missions')->addSelect('missions')
            ->leftJoin('missions.direction', 'direction')->addSelect('direction')
            ->leftJoin('direction.desserteTerminus', 'directionDesserte')->addSelect('directionDesserte')
            ->leftJoin('directionDesserte.station', 'directionStation')->addSelect('directionStation')
            ->getQuery()
            ->getResult()
        ;

        $parId = [];
        foreach ($troncons as $troncon) {
            $parId[$troncon->getId()] = $troncon;
        }

        return array_values(array_filter(array_map(static fn (int $id) => $parId[$id] ?? null, $ids)));
    }

    /**
     * Pour le fond de carte du calculateur de trajet (TrajetController) : chaque paire
     * depart/arrivee de chaque troncon avec les coordonnees geographiques des deux Stations et la
     * couleur de la Ligne - strictement ce dont la carte a besoin, en SQL brut plutot que via
     * l'ORM (findAllWithDetails() charge tout le graphe missions/direction, inutile ici, et
     * hydrater ~193000 entites pour ~7000 troncons prenait plus de 10s).
     *
     * @return list<array{id_a: int, lat1: float, lon1: float, id_b: int, lat2: float, lon2: float, couleur: string}>
     */
    public function tronconsPourCarte(): array
    {
        return $this->getEntityManager()->getConnection()->executeQuery(
            <<<'SQL'
                SELECT sa.id AS id_a, sa.latitude AS lat1, sa.longitude AS lon1,
                       sb.id AS id_b, sb.latitude AS lat2, sb.longitude AS lon2,
                       l.couleur AS couleur
                FROM troncon_desserte tda
                JOIN type_desserte tta ON tta.id = tda.type_desserte_id AND tta.label = 'Départ'
                JOIN troncon_desserte tdb ON tdb.troncon_id = tda.troncon_id AND tdb.desserte_id != tda.desserte_id
                JOIN type_desserte ttb ON ttb.id = tdb.type_desserte_id AND ttb.label = 'Arrivée'
                JOIN desserte da ON da.id = tda.desserte_id
                JOIN desserte db ON db.id = tdb.desserte_id
                JOIN station sa ON sa.id = da.station_id
                JOIN station sb ON sb.id = db.station_id
                JOIN ligne l ON l.id = da.ligne_id
                WHERE sa.latitude IS NOT NULL AND sb.latitude IS NOT NULL
                SQL
        )->fetchAllAssociative();
    }

    /**
     * Pour l'index/l'affichage : evite le N+1 sur le graphe depart/arrivee/direction
     * (troncon_desserte -> desserte -> station/ligne, et missions -> direction -> station).
     *
     * @return Troncon[]
     */
    public function findAllWithDetails(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.typeTroncon', 'typeTroncon')->addSelect('typeTroncon')
            ->leftJoin('t.tronconDessertes', 'td')->addSelect('td')
            ->leftJoin('td.desserte', 'd')->addSelect('d')
            ->leftJoin('d.station', 'station')->addSelect('station')
            ->leftJoin('d.ligne', 'ligne')->addSelect('ligne')
            ->leftJoin('td.typeDesserte', 'typeDesserte')->addSelect('typeDesserte')
            ->leftJoin('td.missions', 'missions')->addSelect('missions')
            ->leftJoin('missions.direction', 'direction')->addSelect('direction')
            ->leftJoin('direction.desserteTerminus', 'directionDesserte')->addSelect('directionDesserte')
            ->leftJoin('directionDesserte.station', 'directionStation')->addSelect('directionStation')
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return Troncon[] Returns an array of Troncon objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Troncon
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
