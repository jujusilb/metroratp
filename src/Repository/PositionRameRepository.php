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
     * Pour la page d'une Station : tous les conseils de positionnement qui s'y appliquent,
     * groupes implicitement par Ligne a l'affichage (evite le N+1 sur ligne/acces).
     *
     * @return PositionRame[]
     */
    public function trouverParStation(int $stationId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.station = :stationId')
            ->setParameter('stationId', $stationId)
            ->leftJoin('p.ligne', 'ligne')->addSelect('ligne')
            ->leftJoin('p.acces', 'acces')->addSelect('acces')
            ->orderBy('ligne.label', 'ASC')
            ->addOrderBy('p.destination', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
