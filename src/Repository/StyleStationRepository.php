<?php

namespace App\Repository;

use App\Entity\StyleStation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StyleStation>
 */
class StyleStationRepository extends ServiceEntityRepository
{
    use FiltreAlphabetTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StyleStation::class);
    }

//    /**
//     * @return StyleStation[] Returns an array of StyleStation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?StyleStation
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
