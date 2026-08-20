<?php

namespace App\Repository;

use App\Entity\ArretTransporteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArretTransporteur>
 */
class ArretTransporteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArretTransporteur::class);
    }
}
