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
     * @param string[] $modes cles Ligne::getModeFiltre() a inclure ; [] = aucun resultat
     */
    public function creerRequeteFiltree(array $modes, ?string $recherche): QueryBuilder
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
            $qb->andWhere(
                'EXISTS (SELECT 1 FROM App\Entity\TronconDesserte tdRech
                    JOIN tdRech.desserte dRech
                    JOIN dRech.station sRech
                    WHERE tdRech.troncon = t AND sRech.label LIKE :recherche)'
            )->setParameter('recherche', '%'.trim($recherche).'%');
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
