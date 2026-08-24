<?php

namespace App\Repository;

use App\Entity\Defibrillateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Defibrillateur>
 */
class DefibrillateurRepository extends ServiceEntityRepository
{
    use FiltreAlphabetTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Defibrillateur::class);
    }
}
