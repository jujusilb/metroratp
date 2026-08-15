<?php

namespace App\Repository;

use App\Entity\DocumentLigne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentLigne>
 */
class DocumentLigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentLigne::class);
    }

    public function trouverParUrl(string $url): ?DocumentLigne
    {
        return $this->findOneBy(['url' => $url]);
    }
}
