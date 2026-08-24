<?php

namespace App\Repository;

use App\Entity\Acces;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Acces>
 */
class AccesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Acces::class);
    }

    /**
     * Pour l'index (2500+ Acces) : filtre par nom d'Acces, numero, ou nom de la Station desservie
     * (ex: "Nation" remonte tous les acces de la station Nation ; "3" remonte tous les acces
     * numerotes 3). Le nom de station est filtre via une sous-requete EXISTS plutot qu'un JOIN
     * classique : un Acces peut desservir plusieurs Station (correspondance), et un JOIN
     * multiplierait les lignes SQL renvoyees — faussant le compte total de KnpPaginatorBundle
     * (meme piege que documente dans DesserteRepository::creerRequeteFiltree()).
     */
    public function creerRequeteFiltree(?string $recherche, ?string $lettre = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.styleAcces', 'styleAcces')->addSelect('styleAcces')
            ->orderBy('a.label', 'ASC')
        ;

        if (null !== $recherche && '' !== trim($recherche)) {
            $qb->andWhere(
                'a.label LIKE :recherche OR a.numero LIKE :recherche OR EXISTS ('.
                'SELECT 1 FROM App\\Entity\\Sortie sortie JOIN sortie.station station '.
                'WHERE sortie.acces = a AND station.label LIKE :recherche'.
                ')'
            )->setParameter('recherche', '%'.trim($recherche).'%')
            ;
        }

        if (null !== $lettre && '' !== $lettre) {
            $qb->andWhere('a.label LIKE :lettre')
                ->setParameter('lettre', $lettre.'%')
            ;
        }

        return $qb;
    }

//    /**
//     * @return Acces[] Returns an array of Acces objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Acces
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
