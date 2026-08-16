<?php

namespace App\Repository;

use App\Entity\Sortie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sortie>
 */
class SortieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sortie::class);
    }

    /**
     * Pour l'index : evite le N+1 sur acces/station, affiches sur chaque ligne. Retourne un
     * QueryBuilder (pas ->getResult()) pour rester paginable - meme piege deja rencontre et
     * corrige sur CorrespondanceRepository::findAllWithDetails() (voir TODO.md).
     */
    public function creerRequeteAvecDetails(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.acces', 'acces')->addSelect('acces')
            ->leftJoin('s.station', 'station')->addSelect('station')
            ->orderBy('station.label', 'ASC')
        ;
    }

//    /**
//     * @return Sortie[] Returns an array of Sortie objects
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

//    public function findOneBySomeField($value): ?Sortie
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
