<?php

namespace App\Service;

use App\Repository\DesserteRepository;
use App\Service\Trajet\Etape;
use App\Service\Trajet\ResultatTrajet;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule le plus court chemin entre deux dessertes (algorithme de Dijkstra), sur un graphe
 * ou les noeuds sont les dessertes (un quai = station+ligne) et les aretes sont :
 *  - les troncons (rester sur la meme ligne), dont le poids est la duree reelle calculee a
 *    partir des horaires theoriques GTFS IDFM (Troncon::getDureeReelleSecondes(), voir la
 *    commande app:importer-durees-troncon) quand elle est connue (369 troncons sur 376),
 *    sinon le poids fixe DUREE_TRONCON_MINUTES par defaut ;
 *  - les correspondances (changer de ligne a la meme station), dont le poids cumule :
 *      1. le temps de marche estime (distance renseignee, sinon DUREE_CORRESPONDANCE_DEFAUT_MINUTES) ;
 *      2. une penalite d'attente du prochain train, DUREE_ATTENTE_CORRESPONDANCE_MINUTES.
 *
 * Sans cette penalite d'attente, le modele sous-estimait fortement le cout reel d'un
 * changement de ligne (juste la marche), ce qui poussait l'algorithme a preferer des trajets
 * a plusieurs correspondances alors qu'un trajet direct est en pratique souvent plus rapide.
 * La RATP annonce un intervalle moyen entre rames d'environ 2 min aux heures de pointe et
 * 4 min aux heures creuses (source : Paris ZigZag). Pour une arrivee aleatoire sur le quai,
 * l'attente moyenne theorique est la moitie de cet intervalle, soit ~1.5 a 2 min : on retient
 * 2 min comme estimation raisonnable, pas une donnee officielle RATP.
 *
 * Le graphe (~7000 troncons, ~155000 correspondances) est construit en SQL brut (id + poids
 * seulement), jamais via l'ORM : hydrater l'integralite via Doctrine (comme avant) chargeait des
 * dizaines de milliers d'entites Troncon/Correspondance/Desserte/Ligne a CHAQUE calcul de trajet,
 * ~12s par requete et depuis l'ajout de Ligne::trace (potentiellement volumineux), un
 * "Allowed memory size exhausted" pur et simple. Seules les quelques dizaines de Desserte
 * effectivement utilisees dans le chemin TROUVE sont rechargees via l'ORM a la fin (pattern
 * "requete legere + recharge par ids" deja utilise ailleurs dans le projet pour la pagination).
 */
class TrajetFinder
{
    private const DUREE_TRONCON_MINUTES = 2.0;
    private const DUREE_CORRESPONDANCE_DEFAUT_MINUTES = 3.0;
    private const DUREE_ATTENTE_CORRESPONDANCE_MINUTES = 2.0;

    public function __construct(
        private readonly DesserteRepository $desserteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Origine/destination sont des STATIONS (pas des dessertes precises) : une station "a
     * plusieurs quais" (une desserte par ligne qui la dessert) et on ne demande pas a
     * l'utilisateur de savoir laquelle prendre, l'algorithme part de toutes celles qui existent
     * (filtrees par $modesAutorises) et s'arrete des qu'il en atteint une quelconque a l'arrivee
     * (Dijkstra multi-source/multi-puits : chaque desserte de depart est inseree a distance 0).
     *
     * @param ?string[] $modesAutorises      Cles Ligne::getModeFiltre() a autoriser (metro, tram,
     *                                       rer, bus_ratp, bus_tiers, telepherique, funiculaire).
     *                                       Null ou vide = aucune restriction (comportement
     *                                       historique).
     * @param ?string   $modeEntreeOrigine   Force le point d'entree a un mode precis (ex: entrer
     *                                       a Nation uniquement par le RER) : ne restreint que
     *                                       les dessertes de depart candidates, independamment de
     *                                       $modesAutorises. Le reste du trajet (correspondances,
     *                                       troncons suivants) reste soumis a $modesAutorises
     *                                       normalement, donc le trajet peut tres bien continuer
     *                                       en metro apres etre entre par le RER.
     * @param ?string   $modeEntreeDestination Meme principe, pour le point d'arrivee.
     */
    public function trouverPlusCourtChemin(
        int $stationOrigineId,
        int $stationDestinationId,
        ?array $modesAutorises = null,
        ?string $modeEntreeOrigine = null,
        ?string $modeEntreeDestination = null,
    ): ?ResultatTrajet {
        $dessertesOrigine = $this->dessertesIdsPourStation($stationOrigineId, $modesAutorises, $modeEntreeOrigine);
        $dessertesDestination = $this->dessertesIdsPourStation($stationDestinationId, $modesAutorises, $modeEntreeDestination);

        if ([] === $dessertesOrigine || [] === $dessertesDestination) {
            return null;
        }

        // Meme station des deux cotes (au moins une desserte commune) : rien a parcourir.
        if ([] !== array_intersect($dessertesOrigine, $dessertesDestination)) {
            return new ResultatTrajet([], 0.0);
        }

        [$adjacence, $tronconIdParArc] = $this->construireGraphe($modesAutorises);

        $destinationRecherchee = array_flip($dessertesDestination);
        $distances = [];
        $predecesseurs = [];
        $visites = [];

        $file = new \SplPriorityQueue();
        foreach ($dessertesOrigine as $id) {
            $distances[$id] = 0.0;
            $file->insert($id, 0);
        }

        $arrivee = null;
        while (!$file->isEmpty()) {
            $courant = $file->extract();

            if (isset($visites[$courant])) {
                continue;
            }
            $visites[$courant] = true;

            if (isset($destinationRecherchee[$courant])) {
                $arrivee = $courant;
                break;
            }

            foreach ($adjacence[$courant] ?? [] as $voisin => $poids) {
                if (isset($visites[$voisin])) {
                    continue;
                }

                $nouvelleDistance = $distances[$courant] + $poids;
                if (!isset($distances[$voisin]) || $nouvelleDistance < $distances[$voisin]) {
                    $distances[$voisin] = $nouvelleDistance;
                    $predecesseurs[$voisin] = $courant;
                    // SplPriorityQueue est un tas max : on inverse le poids pour simuler un tas min.
                    $file->insert($voisin, -$nouvelleDistance);
                }
            }
        }

        if (null === $arrivee) {
            return null;
        }

        // Reconstruit le chemin en remontant les predecesseurs, puis le remet dans l'ordre.
        $chemin = [$arrivee];
        $noeud = $arrivee;
        while (isset($predecesseurs[$noeud])) {
            $noeud = $predecesseurs[$noeud];
            $chemin[] = $noeud;
        }
        $chemin = array_reverse($chemin);

        $etapes = $this->construireEtapes($chemin, $adjacence, $tronconIdParArc);

        return new ResultatTrajet($etapes, $distances[$arrivee]);
    }

    /**
     * @param ?string[] $modesAutorises
     * @param ?string   $modeForce Si fourni, ignore $modesAutorises et ne garde que les dessertes
     *                             de ce mode precis (point d'entree/sortie choisi explicitement
     *                             dans l'autocompletion, ex: "Nation (RER)").
     *
     * @return int[]
     */
    private function dessertesIdsPourStation(int $stationId, ?array $modesAutorises, ?string $modeForce = null): array
    {
        $ids = [];
        foreach ($this->desserteRepository->findBy(['station' => $stationId]) as $desserte) {
            $mode = $desserte->getLigne()?->getModeFiltre();

            if (null !== $modeForce) {
                if ($mode === $modeForce) {
                    $ids[] = $desserte->getId();
                }
                continue;
            }

            if (null === $modesAutorises || [] === $modesAutorises || \in_array($mode, $modesAutorises, true)) {
                $ids[] = $desserte->getId();
            }
        }

        return $ids;
    }

    /**
     * Meme logique que Ligne::getModeFiltre(), a partir des libelles bruts (evite de charger des
     * entites Ligne pour l'ensemble du reseau juste pour ce filtre).
     */
    private function modeFiltre(?string $typeTransport, ?string $gestionnaire): ?string
    {
        return match ($typeTransport) {
            'Métro' => 'metro',
            'Tramway' => 'tram',
            'RER' => 'rer',
            'Bus' => 'RATP' === $gestionnaire ? 'bus_ratp' : 'bus_tiers',
            'Téléphérique' => 'telepherique',
            'Funiculaire' => 'funiculaire',
            default => null,
        };
    }

    /**
     * Construit le graphe complet (tous les Troncon/Correspondance du reseau, pas seulement ceux
     * du chemin trouve) en SQL brut : ids et poids seulement, aucune entite ORM chargee. Voir le
     * docblock de la classe.
     *
     * @param ?string[] $modesAutorises
     *
     * @return array{0: array<int, array<int, float>>, 1: array<int, array<int, int>>}
     */
    private function construireGraphe(?array $modesAutorises): array
    {
        /** @var array<int, array<int, float>> $adjacence */
        $adjacence = [];
        /** @var array<int, array<int, int>> $tronconIdParArc */
        $tronconIdParArc = [];

        $modesAutorisesSet = (null === $modesAutorises || [] === $modesAutorises) ? null : array_flip($modesAutorises);
        $modeAutorise = static fn (?string $mode): bool => null === $modesAutorisesSet || isset($modesAutorisesSet[$mode]);

        $ajouterArc = static function (int $departId, int $arriveeId, float $poids) use (&$adjacence): void {
            if (!isset($adjacence[$departId][$arriveeId]) || $poids < $adjacence[$departId][$arriveeId]) {
                $adjacence[$departId][$arriveeId] = $poids;
            }
        };

        $connexion = $this->entityManager->getConnection();

        foreach ($connexion->executeQuery(
            <<<'SQL'
                SELECT tda.desserte_id AS depart_id, tdb.desserte_id AS arrivee_id,
                       t.id AS troncon_id, t.duree_reelle_secondes,
                       tt.label AS type_transport, g.label AS gestionnaire
                FROM troncon_desserte tda
                JOIN type_desserte ttypeA ON ttypeA.id = tda.type_desserte_id AND ttypeA.label = 'Départ'
                JOIN troncon_desserte tdb ON tdb.troncon_id = tda.troncon_id AND tdb.desserte_id != tda.desserte_id
                JOIN type_desserte ttypeB ON ttypeB.id = tdb.type_desserte_id AND ttypeB.label = 'Arrivée'
                JOIN troncon t ON t.id = tda.troncon_id
                JOIN desserte d ON d.id = tda.desserte_id
                JOIN ligne l ON l.id = d.ligne_id
                LEFT JOIN type_transport tt ON tt.id = l.type_transport_id
                LEFT JOIN gestionnaire g ON g.id = l.gestionnaire_id
                SQL
        )->iterateAssociative() as $row) {
            $mode = $this->modeFiltre($row['type_transport'], $row['gestionnaire']);
            if (!$modeAutorise($mode)) {
                continue;
            }

            $departId = (int) $row['depart_id'];
            $arriveeId = (int) $row['arrivee_id'];
            $duree = null !== $row['duree_reelle_secondes']
                ? ((int) $row['duree_reelle_secondes']) / 60
                : self::DUREE_TRONCON_MINUTES;

            $ajouterArc($departId, $arriveeId, $duree);
            $tronconIdParArc[$departId][$arriveeId] = (int) $row['troncon_id'];
        }

        foreach ($connexion->executeQuery(
            <<<'SQL'
                SELECT c.id, c.desserte_a_id, c.desserte_b_id, c.distance,
                       ttA.label AS type_transport_a, gA.label AS gestionnaire_a,
                       ttB.label AS type_transport_b, gB.label AS gestionnaire_b
                FROM correspondance c
                JOIN desserte dA ON dA.id = c.desserte_a_id
                JOIN ligne lA ON lA.id = dA.ligne_id
                LEFT JOIN type_transport ttA ON ttA.id = lA.type_transport_id
                LEFT JOIN gestionnaire gA ON gA.id = lA.gestionnaire_id
                JOIN desserte dB ON dB.id = c.desserte_b_id
                JOIN ligne lB ON lB.id = dB.ligne_id
                LEFT JOIN type_transport ttB ON ttB.id = lB.type_transport_id
                LEFT JOIN gestionnaire gB ON gB.id = lB.gestionnaire_id
                SQL
        )->iterateAssociative() as $row) {
            $modeA = $this->modeFiltre($row['type_transport_a'], $row['gestionnaire_a']);
            $modeB = $this->modeFiltre($row['type_transport_b'], $row['gestionnaire_b']);
            $desserteAId = (int) $row['desserte_a_id'];
            $desserteBId = (int) $row['desserte_b_id'];

            $tempsEstimeMinutes = null !== $row['distance'] ? round(((float) $row['distance']) / 0.9 / 60, 1) : null;
            $duree = ($tempsEstimeMinutes ?? self::DUREE_CORRESPONDANCE_DEFAUT_MINUTES) + self::DUREE_ATTENTE_CORRESPONDANCE_MINUTES;

            if ($modeAutorise($modeB)) {
                $ajouterArc($desserteAId, $desserteBId, $duree);
            }
            if ($modeAutorise($modeA)) {
                $ajouterArc($desserteBId, $desserteAId, $duree);
            }
        }

        return [$adjacence, $tronconIdParArc];
    }

    /**
     * Recharge (une seule requete groupee) les quelques Desserte du chemin TROUVE - jamais
     * l'ensemble du reseau - pour construire les Etape a retourner (station/ligne necessaires a
     * l'affichage).
     *
     * @param int[]                       $chemin
     * @param array<int, array<int, float>> $adjacence
     * @param array<int, array<int, int>>   $tronconIdParArc
     *
     * @return Etape[]
     */
    private function construireEtapes(array $chemin, array $adjacence, array $tronconIdParArc): array
    {
        $dessertes = $this->desserteRepository->trouverAvecDetailsParIds($chemin);
        $dessertesParId = [];
        foreach ($dessertes as $desserte) {
            $dessertesParId[$desserte->getId()] = $desserte;
        }

        $etapes = [];
        for ($i = 0; $i < \count($chemin) - 1; ++$i) {
            $departId = $chemin[$i];
            $arriveeId = $chemin[$i + 1];
            $depart = $dessertesParId[$departId] ?? null;
            $arrivee = $dessertesParId[$arriveeId] ?? null;
            if (null === $depart || null === $arrivee) {
                continue;
            }

            $type = isset($tronconIdParArc[$departId][$arriveeId]) ? Etape::TYPE_TRONCON : Etape::TYPE_CORRESPONDANCE;
            $etapes[] = new Etape($depart, $arrivee, $type, $adjacence[$departId][$arriveeId]);
        }

        return $etapes;
    }
}
