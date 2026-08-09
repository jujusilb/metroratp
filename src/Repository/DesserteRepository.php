<?php

namespace App\Repository;

use App\Entity\Desserte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Desserte>
 */
class DesserteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Desserte::class);
    }

    /**
     * Pour l'autocompletion du trajet : recupere en une requete les dessertes (avec ligne +
     * typeTransport + gestionnaire, necessaires a Ligne::getModeFiltre()) des stations trouvees
     * par StationRepository::rechercherParLabel, pour afficher les modes desservis par chaque
     * suggestion sans un N+1 (une requete par station).
     *
     * @param int[] $stationIds
     *
     * @return Desserte[]
     */
    public function findByStationIds(array $stationIds): array
    {
        if ([] === $stationIds) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->andWhere('d.station IN (:stationIds)')
            ->setParameter('stationIds', $stationIds)
            ->leftJoin('d.ligne', 'ligne')->addSelect('ligne')
            ->leftJoin('ligne.typeTransport', 'typeTransport')->addSelect('typeTransport')
            ->leftJoin('ligne.gestionnaire', 'gestionnaire')->addSelect('gestionnaire')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Pour l'index : evite le N+1 sur station/ligne/styleStation, affichees sur chaque ligne.
     *
     * @return Desserte[]
     */
    public function findAllWithDetails(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.station', 'station')->addSelect('station')
            ->leftJoin('d.ligne', 'ligne')->addSelect('ligne')
            ->leftJoin('d.styleStation', 'styleStation')->addSelect('styleStation')
            ->leftJoin('d.periodesOuverture', 'periodesOuverture')->addSelect('periodesOuverture')
            ->orderBy('ligne.id', 'ASC')
            ->addOrderBy('station.label', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return Desserte[] Returns an array of Desserte objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Desserte
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
