<?php

namespace App\Repository;

use App\Entity\StatutTache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StatutTache>
 */
class StatutTacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatutTache::class);
    }

    public function trouverParLabel(string $label): ?StatutTache
    {
        return $this->findOneBy(['label' => $label]);
    }
}
