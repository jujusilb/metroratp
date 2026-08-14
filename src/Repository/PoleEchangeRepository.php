<?php

namespace App\Repository;

use App\Entity\PoleEchange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PoleEchange>
 */
class PoleEchangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PoleEchange::class);
    }

    public function trouverParCodeExterne(string $codeExterne): ?PoleEchange
    {
        return $this->findOneBy(['codeExterne' => $codeExterne]);
    }
}
