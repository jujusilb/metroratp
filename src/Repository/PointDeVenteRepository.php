<?php

namespace App\Repository;

use App\Entity\PointDeVente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PointDeVente>
 */
class PointDeVenteRepository extends ServiceEntityRepository
{
    use FiltreAlphabetTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PointDeVente::class);
    }

    public function trouverParCodeExterne(string $codeExterne): ?PointDeVente
    {
        return $this->findOneBy(['codeExterne' => $codeExterne]);
    }
}
