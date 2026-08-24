<?php

namespace App\Repository;

use App\Entity\Ligne;
use App\Entity\Ville;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ville>
 */
class VilleRepository extends ServiceEntityRepository
{
    use FiltreAlphabetTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ville::class);
    }

    /**
     * Classe les Ligne touchant cette Ville en 3 categories, selon la position de leurs
     * Desserte situees dans la Ville par rapport au reste de la Ligne :
     * - completementDedans : toutes les Desserte de la Ligne sont dans cette Ville (la ligne
     *   entiere est locale a la commune).
     * - unBoutDehors : au moins une extremite de la Ligne (Desserte qui ne touche qu'un seul
     *   Troncon distinct - terminus de la ligne ou d'une branche, voir
     *   Desserte::getNombreTronconsDistincts()) est dans cette Ville, mais pas toutes ses
     *   Desserte : la ligne commence/finit ici puis continue ailleurs.
     * - traversee : des Desserte de la Ligne sont dans cette Ville, mais aucune n'est une
     *   extremite - la ligne entre et sort de la commune sans qu'aucun bout n'y soit,
     *   simple passage.
     *
     * @return array{completementDedans: Ligne[], unBoutDehors: Ligne[], traversee: Ligne[]}
     */
    public function trouverLignesConcernees(Ville $ville): array
    {
        $connexion = $this->getEntityManager()->getConnection();

        $ligneIds = $connexion->executeQuery(
            'SELECT DISTINCT d.ligne_id FROM desserte d JOIN station s ON s.id = d.station_id WHERE s.ville_ref_id = ?',
            [$ville->getId()],
        )->fetchFirstColumn();

        if ([] === $ligneIds) {
            return ['completementDedans' => [], 'unBoutDehors' => [], 'traversee' => []];
        }

        $rows = $connexion->executeQuery(
            'SELECT d.ligne_id, s.ville_ref_id, COUNT(DISTINCT td.troncon_id) AS nb_troncons
             FROM desserte d
             JOIN station s ON s.id = d.station_id
             LEFT JOIN troncon_desserte td ON td.desserte_id = d.id
             WHERE d.ligne_id IN (:ligneIds)
             GROUP BY d.id, d.ligne_id, s.ville_ref_id',
            ['ligneIds' => array_map('intval', $ligneIds)],
            ['ligneIds' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        $parLigne = [];
        foreach ($rows as $row) {
            $parLigne[(int) $row['ligne_id']][] = $row;
        }

        $ligneRepository = $this->getEntityManager()->getRepository(Ligne::class);
        $resultat = ['completementDedans' => [], 'unBoutDehors' => [], 'traversee' => []];

        foreach ($parLigne as $ligneId => $dessertes) {
            $toutesDedans = true;
            $auMoinsUneExtremiteDedans = false;
            foreach ($dessertes as $desserte) {
                $dansCetteVille = null !== $desserte['ville_ref_id'] && $ville->getId() === (int) $desserte['ville_ref_id'];
                if (!$dansCetteVille) {
                    $toutesDedans = false;
                    continue;
                }
                if ((int) $desserte['nb_troncons'] <= 1) {
                    $auMoinsUneExtremiteDedans = true;
                }
            }

            $ligne = $ligneRepository->find($ligneId);
            if (null === $ligne) {
                continue;
            }

            if ($toutesDedans) {
                $resultat['completementDedans'][] = $ligne;
            } elseif ($auMoinsUneExtremiteDedans) {
                $resultat['unBoutDehors'][] = $ligne;
            } else {
                $resultat['traversee'][] = $ligne;
            }
        }

        return $resultat;
    }
}
