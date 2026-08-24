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
    use FiltreAlphabetTrait;

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
     * Matche aussi par nom de Ville (Station::villeRef), en complement du label de Station -
     * permet de taper un nom de commune (ex: "Coulommiers") et retrouver ses Station meme si
     * aucune ne porte exactement ce nom. Toujours priorise apres un vrai match de label (voir
     * ordre des criteres ci-dessous) : sinon une grande ville comme Paris noierait le resultat de
     * centaines de stations sans rapport direct avec la recherche tapee.
     *
     * Le tri par pertinence se fait en SQL, sur tout le jeu de resultats (pas apres une limite
     * intermediaire arbitraire) : un premier essai limitait a une fenetre triee alphabetiquement
     * avant de re-trier en PHP, ce qui pouvait carrement exclure la station "Chatelet" du lot vu
     * le nombre d'arrets generiques "Chateau ..." qui la precedent alphabetiquement. Criteres,
     * dans l'ordre :
     *  1. match sur le label de la Station elle-meme avant un simple match par nom de Ville ;
     *  2. position du match (prefixe d'abord, via LOCATE) ;
     *  3. desservie par un mode lourd (Metro/RER/Tramway) avant un simple arret de bus : sans ce
     *     critere, les ~42 arrets de bus nommes litteralement "Chateau" (un par commune, donc
     *     tous plus courts que "Chatelet") noient les vraies stations de metro homonymes ;
     *  4. label le plus court (le plus proche d'une correspondance exacte) : "Nation" < "Nations"
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
                LEFT JOIN ville v ON v.id = s.ville_ref_id
                WHERE s.label LIKE :rechercheLike OR v.label LIKE :rechercheLike
                ORDER BY
                    (s.label LIKE :rechercheLike) DESC,
                    CASE WHEN s.label LIKE :rechercheLike THEN LOCATE(:recherche, s.label) ELSE 999 END ASC,
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

    /**
     * Pour tout import qui rattache une donnee externe a une Station par ZdC (Acces/Sortie,
     * PositionRame...) : renvoie, pour chaque codeExterne connu, l'id de la Station a utiliser
     * REELLEMENT - celle de la Station "originale" homonyme (creee a la main avant
     * app:importer-reseau-complet, sans codeExterne, voir TODO.md) quand elle existe, sinon celle
     * de la Station ZdC-liee elle-meme.
     *
     * Necessaire car les Desserte/Troncon les plus anciens (tout le reseau metro/RER/tram
     * "historique") pointent vers la Station "originale", pas vers son doublon ZdC-lie cree plus
     * tard : sans ce rattachement, une donnee importee par ZdC atterrirait sur une Station que
     * personne ne consulte jamais en pratique (la page /station/{id} vraiment visitee est celle de
     * l'originale). Verifie sans ambiguite (aucun cas ou plusieurs originales homonymes existent).
     *
     * @return array<string, int> codeExterne => id de Station a utiliser
     */
    public function trouverIdCanoniqueParZdc(): array
    {
        $connexion = $this->getEntityManager()->getConnection();

        $resultat = [];
        foreach ($connexion->executeQuery(
            <<<'SQL'
                SELECT zdc.code_externe AS code_externe, COALESCE(MIN(originale.id), zdc.id) AS id
                FROM station zdc
                LEFT JOIN station originale ON originale.label = zdc.label AND originale.code_externe IS NULL AND originale.id != zdc.id
                WHERE zdc.code_externe IS NOT NULL
                GROUP BY zdc.id
                SQL
        )->iterateAssociative() as $row) {
            $resultat[$row['code_externe']] = (int) $row['id'];
        }

        return $resultat;
    }

    /**
     * Pour la carte complete du reseau (toutes les Stations, tous modes) : chaque Station avec
     * coordonnees connues, et la liste des (mode, ligne, gestionnaire) qui la desservent - de quoi
     * construire une bulle "Mode:Ligne:Arret" au survol de chaque point. En SQL brut (comme
     * TronconRepository::tronconsPourCarte()) : ~31500 lignes desserte/station a agreger, hors de
     * question de le faire via l'ORM (voir TrajetFinder, corrige le 2026-08-13 pour la meme raison).
     *
     * @return list<array{id: int, label: string, lat: float, lon: float, dessertes: list<array{mode: ?string, ligne: string, ligneId: int, gestionnaire: ?string}>}>
     */
    public function donneesPourCarteComplete(): array
    {
        $connexion = $this->getEntityManager()->getConnection();

        $parStation = [];
        foreach ($connexion->executeQuery(
            <<<'SQL'
                SELECT s.id, s.label, s.latitude, s.longitude,
                       tt.label AS mode, l.id AS ligne_id, l.label AS ligne_label, g.label AS gestionnaire_label
                FROM station s
                JOIN desserte d ON d.station_id = s.id
                JOIN ligne l ON l.id = d.ligne_id
                LEFT JOIN type_transport tt ON tt.id = l.type_transport_id
                LEFT JOIN gestionnaire g ON g.id = l.gestionnaire_id
                WHERE s.latitude IS NOT NULL
                ORDER BY s.id
                SQL
        )->iterateAssociative() as $row) {
            $id = (int) $row['id'];
            if (!isset($parStation[$id])) {
                $parStation[$id] = [
                    'id' => $id,
                    'label' => $row['label'],
                    'lat' => (float) $row['latitude'],
                    'lon' => (float) $row['longitude'],
                    'dessertes' => [],
                ];
            }
            $parStation[$id]['dessertes'][] = [
                'mode' => $row['mode'],
                'ligne' => $row['ligne_label'],
                'ligneId' => (int) $row['ligne_id'],
                // n'affiche le gestionnaire que quand ce n'est pas RATP (implicite sinon) - voir
                // Ligne::getModeFiltre() qui fait la meme distinction bus_ratp/bus_tiers.
                'gestionnaire' => 'RATP' !== $row['gestionnaire_label'] ? $row['gestionnaire_label'] : null,
            ];
        }

        return array_values($parStation);
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
