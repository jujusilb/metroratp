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
     * @param string[] $modes         cles Ligne::getModeFiltre() a inclure ; [] = aucun resultat
     * @param ?string  $recherche      filtre par numero/nom de ligne OU nom de gestionnaire (LIKE
     *                                 sur Ligne::label/Gestionnaire::label, pas sur les stations
     *                                 desservies : taper "2" doit remonter la ligne "2" du metro,
     *                                 "20" a "29" et "200" a "299" du bus, "342", etc. ; taper
     *                                 "Keolis" doit remonter toutes les lignes de ce gestionnaire)
     * @param int[]    $gestionnaireIds identifiants Gestionnaire a inclure ; [] = pas de filtre
     *                                 (contrairement aux modes, une liste a choix multiple vide
     *                                 signifie "aucune restriction", pas "aucun resultat")
     * @param ?bool    $avecTroncons   true = seulement les lignes ayant deja des troncons
     *                                 construits, false = seulement celles qui n'en ont aucun,
     *                                 null = pas de filtre
     */
    public function creerRequeteFiltree(array $modes, ?string $recherche, array $gestionnaireIds = [], ?bool $avecTroncons = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.typeTransport', 'tt')->addSelect('tt')
            ->leftJoin('l.gestionnaire', 'g')->addSelect('g')
            ->orderBy('l.id', 'ASC')
        ;

        $this->appliquerFiltreModes($qb, $modes);

        if (null !== $recherche && '' !== trim($recherche)) {
            $qb->andWhere('l.label LIKE :recherche OR g.label LIKE :recherche')
                ->setParameter('recherche', '%'.trim($recherche).'%')
            ;
        }

        if ([] !== $gestionnaireIds) {
            $qb->andWhere('g.id IN (:gestionnaireIds)')
                ->setParameter('gestionnaireIds', $gestionnaireIds)
            ;
        }

        if (null !== $avecTroncons) {
            $exists = 'EXISTS (SELECT 1 FROM App\Entity\Desserte dTroncon
                JOIN dTroncon.tronconDessertes tdTroncon
                WHERE dTroncon.ligne = l)';
            $qb->andWhere($avecTroncons ? $exists : 'NOT '.$exists);
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
        if (\in_array('telepherique', $modes, true)) {
            $conditions[] = "tt.label = 'Téléphérique'";
        }
        if (\in_array('funiculaire', $modes, true)) {
            $conditions[] = "tt.label = 'Funiculaire'";
        }
        if (\in_array('train', $modes, true)) {
            $conditions[] = "tt.label = 'Train'";
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
