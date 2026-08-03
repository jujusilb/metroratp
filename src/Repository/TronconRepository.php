<?php

namespace App\Repository;

use App\Entity\Troncon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
