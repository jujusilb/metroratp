<?php

namespace App\Repository;

use App\Entity\Direction;
use App\Entity\Ligne;
use App\Entity\Mission;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mission>
 */
class MissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mission::class);
    }

    /**
     * Lignes ayant au moins une mission, pour l'ecran de selection.
     *
     * @return Ligne[]
     */
    public function findLignesWithMissions(): array
    {
        $em = $this->getEntityManager();
        $subQuery = $em->createQueryBuilder()
            ->select('m2.id')
            ->from(Mission::class, 'm2')
            ->innerJoin('m2.tronconDesserte', 'td2')
            ->innerJoin('td2.desserte', 'depart2')
            ->where('depart2.ligne = ligne')
            ->getDQL();

        return $em->createQueryBuilder()
            ->select('ligne')
            ->from(Ligne::class, 'ligne')
            ->where('EXISTS (' . $subQuery . ')')
            ->orderBy('ligne.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Les directions vers lesquelles il existe des missions sur cette ligne.
     *
     * @return Direction[]
     */
    public function findDirectionsForLigne(int $ligneId): array
    {
        $em = $this->getEntityManager();
        $subQuery = $em->createQueryBuilder()
            ->select('m2.id')
            ->from(Mission::class, 'm2')
            ->where('m2.direction = direction')
            ->getDQL();

        return $em->createQueryBuilder()
            ->select('direction')
            ->addSelect('directionDesserte')
            ->addSelect('directionStation')
            ->from(Direction::class, 'direction')
            ->innerJoin('direction.desserteTerminus', 'directionDesserte')
            ->innerJoin('directionDesserte.station', 'directionStation')
            ->where('direction.ligne = :ligneId')
            ->andWhere('EXISTS (' . $subQuery . ')')
            ->setParameter('ligneId', $ligneId)
            ->orderBy('directionStation.label', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Les services qui desservent cette direction sur cette ligne (plus d'un = embranchement,
     * chaque service menant a un terminus different au sein de cette meme direction generale).
     *
     * @return Service[]
     */
    public function findServicesForDirection(int $ligneId, int $directionId): array
    {
        $em = $this->getEntityManager();
        $subQuery = $em->createQueryBuilder()
            ->select('m2.id')
            ->from(Mission::class, 'm2')
            ->innerJoin('m2.tronconDesserte', 'td2')
            ->innerJoin('td2.desserte', 'depart2')
            ->where('m2.service = service')
            ->andWhere('m2.direction = :directionId')
            ->andWhere('depart2.ligne = :ligneId')
            ->getDQL();

        return $em->createQueryBuilder()
            ->select('service')
            ->from(Service::class, 'service')
            ->where('EXISTS (' . $subQuery . ')')
            ->setParameter('ligneId', $ligneId)
            ->setParameter('directionId', $directionId)
            ->orderBy('service.label', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Le trajet complet, dans l'ordre, pour une ligne/direction/service donnes.
     *
     * @return Mission[]
     */
    public function findJourney(int $ligneId, int $directionId, int $serviceId): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.tronconDesserte', 'td')->addSelect('td')
            ->innerJoin('td.troncon', 'troncon')->addSelect('troncon')
            ->innerJoin('td.desserte', 'depart')->addSelect('depart')
            ->innerJoin('depart.station', 'departStation')->addSelect('departStation')
            ->leftJoin('troncon.tronconDessertes', 'allTd')->addSelect('allTd')
            ->leftJoin('allTd.desserte', 'allTdDesserte')->addSelect('allTdDesserte')
            ->leftJoin('allTdDesserte.station', 'allTdStation')->addSelect('allTdStation')
            ->leftJoin('allTd.typeDesserte', 'allTdType')->addSelect('allTdType')
            ->where('depart.ligne = :ligneId')
            ->andWhere('m.direction = :directionId')
            ->andWhere('m.service = :serviceId')
            ->setParameter('ligneId', $ligneId)
            ->setParameter('directionId', $directionId)
            ->setParameter('serviceId', $serviceId)
            ->orderBy('m.numero', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
