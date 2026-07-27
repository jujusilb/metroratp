<?php

namespace App\Repository;

use App\Entity\PeriodeOuverture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PeriodeOuverture>
 */
class PeriodeOuvertureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PeriodeOuverture::class);
    }

    /**
     * Pour l'index : evite le N+1 sur desserte/station/ligne, affichees sur chaque ligne.
     *
     * @return PeriodeOuverture[]
     */
    public function findAllWithDetails(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.desserte', 'desserte')->addSelect('desserte')
            ->leftJoin('desserte.station', 'station')->addSelect('station')
            ->leftJoin('desserte.ligne', 'ligne')->addSelect('ligne')
            ->orderBy('station.label', 'ASC')
            ->addOrderBy('p.ordre', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
