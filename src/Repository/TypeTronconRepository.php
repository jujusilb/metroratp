<?php

namespace App\Repository;

use App\Entity\TypeTroncon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeTroncon>
 */
class TypeTronconRepository extends ServiceEntityRepository
{
    use FiltreAlphabetTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeTroncon::class);
    }

    /**
     * Pour la fiche (jusqu'a plusieurs centaines de troncons, ex: "Interieur") : evite le N+1
     * sur le graphe depart/arrivee/direction de chaque troncon.
     */
    public function findWithTronconsDetails(int $id): ?TypeTroncon
    {
        return $this->createQueryBuilder('tt')
            ->leftJoin('tt.troncons', 't')->addSelect('t')
            ->leftJoin('t.tronconDessertes', 'td')->addSelect('td')
            ->leftJoin('td.desserte', 'd')->addSelect('d')
            ->leftJoin('d.station', 'station')->addSelect('station')
            ->leftJoin('td.typeDesserte', 'typeDesserte')->addSelect('typeDesserte')
            ->leftJoin('td.missions', 'missions')->addSelect('missions')
            ->leftJoin('missions.direction', 'direction')->addSelect('direction')
            ->leftJoin('direction.desserteTerminus', 'directionDesserte')->addSelect('directionDesserte')
            ->leftJoin('directionDesserte.station', 'directionStation')->addSelect('directionStation')
            ->where('tt.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

//    /**
//     * @return TypeTroncon[] Returns an array of TypeTroncon objects
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

//    public function findOneBySomeField($value): ?TypeTroncon
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
