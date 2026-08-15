<?php

namespace App\Repository;

use App\Entity\Correspondance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Correspondance>
 */
class CorrespondanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Correspondance::class);
    }

    /**
     * Pour l'index (paginee) : evite le N+1 sur desserteA/desserteB -> station/ligne, affiches
     * sur chaque ligne. Retourne un QueryBuilder (pas getResult()) : la table compte 100000+
     * lignes, un chargement complet (meme avec pagination applicable) epuisait la memoire PHP
     * en prod avant ce correctif - voir CorrespondanceController::index().
     */
    public function creerRequeteAvecDetails(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.desserteA', 'a')->addSelect('a')
            ->leftJoin('a.station', 'aStation')->addSelect('aStation')
            ->leftJoin('a.ligne', 'aLigne')->addSelect('aLigne')
            ->leftJoin('c.desserteB', 'b')->addSelect('b')
            ->leftJoin('b.station', 'bStation')->addSelect('bStation')
            ->leftJoin('b.ligne', 'bLigne')->addSelect('bLigne')
            ->leftJoin('c.directionA', 'dirA')->addSelect('dirA')
            ->leftJoin('dirA.desserteTerminus', 'dirADesserte')->addSelect('dirADesserte')
            ->leftJoin('dirADesserte.station', 'dirAStation')->addSelect('dirAStation')
            ->leftJoin('c.directionB', 'dirB')->addSelect('dirB')
            ->leftJoin('dirB.desserteTerminus', 'dirBDesserte')->addSelect('dirBDesserte')
            ->leftJoin('dirBDesserte.station', 'dirBStation')->addSelect('dirBStation')
            ->orderBy('aStation.label', 'ASC')
        ;
    }

    /**
     * Vraie s'il existe deja une correspondance pour exactement cette combinaison (peu importe
     * l'ordre A/B, puisque l'ordre canonique n'est applique qu'au PrePersist). Sert a donner un
     * message d'erreur clair plutot que de laisser echouer la contrainte UNIQUE en base, qui ne
     * traite pas deux NULL comme identiques (donc ne bloquerait pas un vrai doublon "general").
     */
    public function existeDejaPourCombinaison(
        int $desserteAId,
        int $desserteBId,
        ?int $directionAId,
        ?int $directionBId,
        ?int $excludingId = null,
    ): bool {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where(
                '(c.desserteA = :dA AND c.desserteB = :dB AND '.$this->directionMatch('directionA', $directionAId, 'dirA').' AND '.$this->directionMatch('directionB', $directionBId, 'dirB').')'
                .' OR '
                .'(c.desserteA = :dB AND c.desserteB = :dA AND '.$this->directionMatch('directionA', $directionBId, 'dirBAsA').' AND '.$this->directionMatch('directionB', $directionAId, 'dirAAsB').')'
            )
            ->setParameter('dA', $desserteAId)
            ->setParameter('dB', $desserteBId)
        ;

        foreach (['dirA' => $directionAId, 'dirB' => $directionBId, 'dirBAsA' => $directionBId, 'dirAAsB' => $directionAId] as $param => $value) {
            if (null !== $value) {
                $qb->setParameter($param, $value);
            }
        }

        if (null !== $excludingId) {
            $qb->andWhere('c.id != :excludingId')->setParameter('excludingId', $excludingId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    private function directionMatch(string $field, ?int $value, string $param): string
    {
        return null === $value ? sprintf('c.%s IS NULL', $field) : sprintf('c.%s = :%s', $field, $param);
    }
}
