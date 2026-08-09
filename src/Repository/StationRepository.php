<?php

namespace App\Repository;

use App\Entity\Station;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Station>
 */
class StationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Station::class);
    }

    /**
     * Pour l'autocompletion du formulaire de trajet : une station = un lieu reel (voir
     * Station::$codeExterne), donc une simple recherche par label est deja dedupliquee
     * (contrairement a une recherche par Desserte, qui donnerait une ligne par ligne desservant
     * la station).
     *
     * Le tri par pertinence se fait en SQL, sur tout le jeu de resultats (pas apres une limite
     * intermediaire arbitraire) : un premier essai limitait a une fenetre triee alphabetiquement
     * avant de re-trier en PHP, ce qui pouvait carrement exclure la station "Chatelet" du lot vu
     * le nombre d'arrets generiques "Chateau ..." qui la precedent alphabetiquement. Criteres,
     * dans l'ordre :
     *  1. position du match (prefixe d'abord, via LOCATE) ;
     *  2. desservie par un mode lourd (Metro/RER/Tramway) avant un simple arret de bus : sans ce
     *     critere, les ~42 arrets de bus nommes litteralement "Chateau" (un par commune, donc
     *     tous plus courts que "Chatelet") noient les vraies stations de metro homonymes ;
     *  3. label le plus court (le plus proche d'une correspondance exacte) : "Nation" < "Nations"
     *     < "National ..." < "Assemblee Nationale".
     * Necessite du SQL natif (sous-requete EXISTS dans l'ORDER BY, hors grammaire DQL) ; les
     * entites sont ensuite rechargees via l'ORM en respectant cet ordre (results ORM = cache
     * d'identite, coherent avec le reste de l'appli).
     *
     * @return Station[]
     */
    public function rechercherParLabel(string $recherche, int $limite = 25): array
    {
        $connexion = $this->getEntityManager()->getConnection();

        $ids = $connexion->executeQuery(
            <<<'SQL'
                SELECT s.id
                FROM station s
                WHERE s.label LIKE :rechercheLike
                ORDER BY
                    LOCATE(:recherche, s.label) ASC,
                    (EXISTS (
                        SELECT 1 FROM desserte d
                        INNER JOIN ligne l ON l.id = d.ligne_id
                        INNER JOIN type_transport t ON t.id = l.type_transport_id
                        WHERE d.station_id = s.id AND t.label IN ('Métro', 'RER', 'Tramway')
                    )) DESC,
                    LENGTH(s.label) ASC,
                    s.label ASC
                LIMIT :limite
                SQL,
            [
                'rechercheLike' => '%' . $recherche . '%',
                'recherche' => $recherche,
                'limite' => $limite,
            ],
            ['limite' => ParameterType::INTEGER],
        )->fetchFirstColumn();
        $ids = array_map('intval', $ids);

        if ([] === $ids) {
            return [];
        }

        $stations = $this->createQueryBuilder('s')
            ->andWhere('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;

        $stationParId = [];
        foreach ($stations as $station) {
            $stationParId[$station->getId()] = $station;
        }

        // Le "IN (...)" ne garantit pas l'ordre : on reapplique celui calcule en SQL.
        return array_values(array_filter(array_map(
            static fn (int $id): ?Station => $stationParId[$id] ?? null,
            $ids,
        )));
    }

//    /**
//     * @return Station[] Returns an array of Station objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Station
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
