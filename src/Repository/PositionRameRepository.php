<?php

namespace App\Repository;

use App\Entity\PositionRame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PositionRame>
 */
class PositionRameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PositionRame::class);
    }

    /**
     * Pour le calculateur de trajet : conseils de positionnement a l'arrivee d'un troncon (avant
     * de changer de ligne ou d'arriver a destination), pour CETTE Ligne precise - plus utile ici,
     * en contexte d'un trajet reel, que sur la fiche Station seule (ou "pour rejoindre" n'a pas de
     * sens sans savoir vers ou l'on va).
     *
     * @return PositionRame[]
     */
    public function trouverParStationEtLigne(int $stationId, int $ligneId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.station = :stationId')
            ->andWhere('p.ligne = :ligneId')
            ->setParameter('stationId', $stationId)
            ->setParameter('ligneId', $ligneId)
            ->leftJoin('p.acces', 'acces')->addSelect('acces')
            ->orderBy('p.destination', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
