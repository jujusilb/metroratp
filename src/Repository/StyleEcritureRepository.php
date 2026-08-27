<?php

namespace App\Repository;

use App\Entity\StyleEcriture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StyleEcriture>
 */
class StyleEcritureRepository extends ServiceEntityRepository
{
    use FiltreAlphabetTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StyleEcriture::class);
    }
}
