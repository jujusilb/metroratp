<?php

namespace App\Repository;

use App\Entity\Desserte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Desserte>
 */
class DesserteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Desserte::class);
    }

    /**
     * Pour l'index (des milliers de dessertes au total) : filtre par mode de la ligne (meme
     * notion que Ligne::getModeFiltre()) et par nom de station, a paginer avec KnpPaginatorBundle.
     *
     * Volontairement AUCUN fetch-join sur une collection ici (periodesOuverture, notamment) :
     * KnpPaginatorBundle pagine sur le nombre de LIGNES SQL renvoyees par la requete, donc un
     * fetch-join qui multiplie les lignes (une desserte avec 3 periodes = 3 lignes SQL) fausserait
     * le compte total et la pagination. Voir DesserteController::index() pour le second temps
     * (rechargement avec details, seulement pour les ids de la page courante).
     *
     * @param string[] $modes cles Ligne::getModeFiltre() a inclure ; [] = aucun resultat
     */
    public function creerRequeteFiltree(array $modes, ?string $recherche): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.ligne', 'ligne')
            ->leftJoin('ligne.typeTransport', 'tt')
            ->leftJoin('ligne.gestionnaire', 'g')
            ->orderBy('ligne.id', 'ASC')
            ->addOrderBy('d.id', 'ASC')
        ;

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
        $qb->andWhere([] !== $conditions ? implode(' OR ', $conditions) : '1 = 0');

        if (null !== $recherche && '' !== trim($recherche)) {
            $qb->leftJoin('d.station', 'station')
                ->andWhere('station.label LIKE :recherche')
                ->setParameter('recherche', '%'.trim($recherche).'%')
            ;
        }

        return $qb;
    }

    /**
     * Recharge en une requete (avec les jointures d'affichage) les dessertes de la page courante,
     * dans l'ordre des ids donnes (celui de la pagination) — voir creerRequeteFiltree().
     *
     * @param int[] $ids
     *
     * @return Desserte[]
     */
    public function trouverAvecDetailsParIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $dessertes = $this->createQueryBuilder('d')
            ->andWhere('d.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->leftJoin('d.station', 'station')->addSelect('station')
            ->leftJoin('d.ligne', 'ligne')->addSelect('ligne')
            ->leftJoin('d.styleStation', 'styleStation')->addSelect('styleStation')
            ->leftJoin('d.periodesOuverture', 'periodesOuverture')->addSelect('periodesOuverture')
            ->getQuery()
            ->getResult()
        ;

        $parId = [];
        foreach ($dessertes as $desserte) {
            $parId[$desserte->getId()] = $desserte;
        }

        return array_values(array_filter(array_map(static fn (int $id) => $parId[$id] ?? null, $ids)));
    }

    /**
     * Pour l'autocompletion du trajet : recupere en une requete les dessertes (avec ligne +
     * typeTransport + gestionnaire, necessaires a Ligne::getModeFiltre()) des stations trouvees
     * par StationRepository::rechercherParLabel, pour afficher les modes desservis par chaque
     * suggestion sans un N+1 (une requete par station).
     *
     * @param int[] $stationIds
     *
     * @return Desserte[]
     */
    public function findByStationIds(array $stationIds): array
    {
        if ([] === $stationIds) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->andWhere('d.station IN (:stationIds)')
            ->setParameter('stationIds', $stationIds)
            ->leftJoin('d.ligne', 'ligne')->addSelect('ligne')
            ->leftJoin('ligne.typeTransport', 'typeTransport')->addSelect('typeTransport')
            ->leftJoin('ligne.gestionnaire', 'gestionnaire')->addSelect('gestionnaire')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Pour l'index : evite le N+1 sur station/ligne/styleStation, affichees sur chaque ligne.
     *
     * @return Desserte[]
     */
    public function findAllWithDetails(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.station', 'station')->addSelect('station')
            ->leftJoin('d.ligne', 'ligne')->addSelect('ligne')
            ->leftJoin('d.styleStation', 'styleStation')->addSelect('styleStation')
            ->leftJoin('d.periodesOuverture', 'periodesOuverture')->addSelect('periodesOuverture')
            ->orderBy('ligne.id', 'ASC')
            ->addOrderBy('station.label', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return Desserte[] Returns an array of Desserte objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Desserte
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
