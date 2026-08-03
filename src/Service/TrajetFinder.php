<?php

namespace App\Service;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use App\Entity\Troncon;
use App\Repository\CorrespondanceRepository;
use App\Repository\DesserteRepository;
use App\Repository\TronconRepository;
use App\Service\Trajet\Etape;
use App\Service\Trajet\ResultatTrajet;

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
 */
class TrajetFinder
{
    private const DUREE_TRONCON_MINUTES = 2.0;
    private const DUREE_CORRESPONDANCE_DEFAUT_MINUTES = 3.0;
    private const DUREE_ATTENTE_CORRESPONDANCE_MINUTES = 2.0;

    public function __construct(
        private readonly DesserteRepository $desserteRepository,
        private readonly TronconRepository $tronconRepository,
        private readonly CorrespondanceRepository $correspondanceRepository,
    ) {
    }

    public function trouverPlusCourtChemin(int $desserteOrigineId, int $desserteDestinationId): ?ResultatTrajet
    {
        if ($desserteOrigineId === $desserteDestinationId) {
            return new ResultatTrajet([], 0.0);
        }

        [$adjacence, $etapesParArc] = $this->construireGraphe();

        $distances = [$desserteOrigineId => 0.0];
        $predecesseurs = [];
        $visites = [];

        $file = new \SplPriorityQueue();
        $file->insert($desserteOrigineId, 0);

        while (!$file->isEmpty()) {
            $courant = $file->extract();

            if (isset($visites[$courant])) {
                continue;
            }
            $visites[$courant] = true;

            if ($courant === $desserteDestinationId) {
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

        if (!isset($distances[$desserteDestinationId])) {
            return null;
        }

        // Reconstruit le chemin en remontant les predecesseurs, puis le remet dans l'ordre.
        $chemin = [$desserteDestinationId];
        $noeud = $desserteDestinationId;
        while (isset($predecesseurs[$noeud])) {
            $noeud = $predecesseurs[$noeud];
            $chemin[] = $noeud;
        }
        $chemin = array_reverse($chemin);

        $etapes = [];
        for ($i = 0; $i < count($chemin) - 1; $i++) {
            $etapes[] = $etapesParArc[$chemin[$i]][$chemin[$i + 1]];
        }

        return new ResultatTrajet($etapes, $distances[$desserteDestinationId]);
    }

    /**
     * @return array{0: array<int, array<int, float>>, 1: array<int, array<int, Etape>>}
     */
    private function construireGraphe(): array
    {
        /** @var array<int, array<int, float>> $adjacence */
        $adjacence = [];
        /** @var array<int, array<int, Etape>> $etapesParArc */
        $etapesParArc = [];

        $dessertes = [];
        foreach ($this->desserteRepository->findAll() as $desserte) {
            $dessertes[$desserte->getId()] = $desserte;
        }

        $ajouterArc = function (Desserte $depart, Desserte $arrivee, float $poids, Etape $etape) use (&$adjacence, &$etapesParArc): void {
            $departId = $depart->getId();
            $arriveeId = $arrivee->getId();

            if (!isset($adjacence[$departId][$arriveeId]) || $poids < $adjacence[$departId][$arriveeId]) {
                $adjacence[$departId][$arriveeId] = $poids;
                $etapesParArc[$departId][$arriveeId] = $etape;
            }
        };

        foreach ($this->tronconRepository->findAllWithDetails() as $troncon) {
            /** @var Troncon $troncon */
            $duree = null !== $troncon->getDureeReelleSecondes()
                ? $troncon->getDureeReelleSecondes() / 60
                : self::DUREE_TRONCON_MINUTES;

            foreach ($troncon->getSensCirculation() as $sens) {
                if (null === $sens['depart'] || null === $sens['arrivee']) {
                    continue;
                }

                $ajouterArc(
                    $sens['depart'],
                    $sens['arrivee'],
                    $duree,
                    new Etape($sens['depart'], $sens['arrivee'], Etape::TYPE_TRONCON, $duree, troncon: $troncon),
                );
            }
        }

        foreach ($this->correspondanceRepository->findAllWithDetails() as $correspondance) {
            /** @var Correspondance $correspondance */
            $desserteA = $correspondance->getDesserteA();
            $desserteB = $correspondance->getDesserteB();
            if (null === $desserteA || null === $desserteB) {
                continue;
            }

            $duree = ($correspondance->getTempsEstimeMinutes() ?? self::DUREE_CORRESPONDANCE_DEFAUT_MINUTES)
                + self::DUREE_ATTENTE_CORRESPONDANCE_MINUTES;

            $ajouterArc($desserteA, $desserteB, $duree, new Etape($desserteA, $desserteB, Etape::TYPE_CORRESPONDANCE, $duree, correspondance: $correspondance));
            $ajouterArc($desserteB, $desserteA, $duree, new Etape($desserteB, $desserteA, Etape::TYPE_CORRESPONDANCE, $duree, correspondance: $correspondance));
        }

        return [$adjacence, $etapesParArc];
    }
}
