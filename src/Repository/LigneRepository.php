<?php

namespace App\Repository;

use App\Entity\Ligne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ligne>
 */
class LigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ligne::class);
    }

    /**
     * Pour l'index (des milliers de lignes au total) : filtre par mode (meme notion que
     * Ligne::getModeFiltre(), traduite en conditions SQL sur typeTransport/gestionnaire puisque
     * modeFiltre lui-meme n'est pas une colonne) et par station desservie, a paginer ensuite avec
     * KnpPaginatorBundle. Query legere (pas de fetch-join sur des collections) : sans risque de
     * multiplier les lignes, contrairement a Desserte/Troncon.
     *
     * @param string[] $modes     cles Ligne::getModeFiltre() a inclure ; [] = aucun resultat
     * @param ?string  $recherche filtre par nom de station desservie (LIKE, insensible a la casse)
     */
    public function creerRequeteFiltree(array $modes, ?string $recherche): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.typeTransport', 'tt')->addSelect('tt')
            ->leftJoin('l.gestionnaire', 'g')->addSelect('g')
            ->orderBy('l.id', 'ASC')
        ;

        $this->appliquerFiltreModes($qb, $modes);

        if (null !== $recherche && '' !== trim($recherche)) {
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\Desserte d2 JOIN d2.station s2 WHERE d2.ligne = l AND s2.label LIKE :recherche)')
                ->setParameter('recherche', '%'.trim($recherche).'%')
            ;
        }

        return $qb;
    }

    /**
     * @param string[] $modes
     */
    private function appliquerFiltreModes(QueryBuilder $qb, array $modes): void
    {
        $conditions = [];
        if (\in_array('metro', $modes, true)) {
            $conditions[] = "tt.label = 'Métro'";
        }
        if (\in_array('tram', $modes, true)) {
            $conditions[] = "tt.label = 'Tramway'";
        }
        if (\in_array('rer', $modes, true)) {
            $conditions[] = "tt.label = 'RER'";
        }
        if (\in_array('bus_ratp', $modes, true)) {
            $conditions[] = "(tt.label = 'Bus' AND g.label = 'RATP')";
        }
        if (\in_array('bus_tiers', $modes, true)) {
            $conditions[] = "(tt.label = 'Bus' AND (g.label IS NULL OR g.label != 'RATP'))";
        }

        // Aucun mode coche : cul-de-sac garanti (meme logique que TrajetController), pas la
        // table entiere par defaut.
        $qb->andWhere([] !== $conditions ? implode(' OR ', $conditions) : '1 = 0');
    }

//    /**
//     * @return Ligne[] Returns an array of Ligne objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('l.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Ligne
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
