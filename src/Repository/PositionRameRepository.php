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
     * Pour le calculateur de trajet : LE conseil de positionnement a l'embarquement d'un troncon
     * (une Ligne, une Station de depart), filtre par le sens de circulation reellement emprunte -
     * identifie par la toute prochaine Station reelle du troncon calcule (prochaineStation),
     * plutot que par la Ligne seule (voir documentation/TODO.md, "Conseils de position dans la
     * rame" : sans ce filtre, les 2 sens opposes d'une meme Station+Ligne se melangeaient).
     *
     * Deduplique par (labelPosition, position, positionMax) : le dataset source contient souvent
     * plusieurs lignes quasi-identiques pour un meme sens (une par sortie/destination visee) qui
     * partagent la meme position une fois le bon sens isole - on ne garde que la premiere.
     *
     * Retourne null si aucun conseil ne correspond a ce sens precis (mieux ne rien afficher
     * qu'un conseil pour le mauvais sens).
     */
    public function trouverPourEmbarquement(int $stationId, int $ligneId, int $prochaineStationId): ?PositionRame
    {
        $resultats = $this->createQueryBuilder('p')
            ->andWhere('p.station = :stationId')
            ->andWhere('p.ligne = :ligneId')
            ->andWhere('p.prochaineStation = :prochaineStationId')
            ->setParameter('stationId', $stationId)
            ->setParameter('ligneId', $ligneId)
            ->setParameter('prochaineStationId', $prochaineStationId)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;

        return $resultats[0] ?? null;
    }
}
