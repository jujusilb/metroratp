<?php

namespace App\Controller;

use App\Entity\Desserte;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Repository\DesserteRepository;
use App\Repository\StationRepository;
use App\Repository\TronconRepository;
use App\Service\Trajet\Etape;
use App\Service\TrajetFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/trajet')]
final class TrajetController extends AbstractController
{
    /** @var string[] */
    private const MODES_DISPONIBLES = ['metro', 'tram', 'rer', 'bus_ratp', 'bus_tiers'];

    #[Route(name: 'app_trajet_index', methods: ['GET'])]
    public function index(Request $request, StationRepository $stationRepository, TronconRepository $tronconRepository, TrajetFinder $trajetFinder): Response
    {
        // Au premier chargement (aucune case n'a encore ete soumise), tout est coche par
        // defaut : comportement historique, non restreint.
        $modesSelectionnes = $request->query->has('modes')
            ? $request->query->all('modes')
            : self::MODES_DISPONIBLES;

        // getInt() leve une exception sur une chaine vide (parametre present mais station pas
        // encore choisie dans l'autocompletion) : on passe par get() + filtrage explicite.
        $origineId = (int) $request->query->get('origine') ?: null;
        $destinationId = (int) $request->query->get('destination') ?: null;

        $stationOrigine = null !== $origineId ? $stationRepository->find($origineId) : null;
        $stationDestination = null !== $destinationId ? $stationRepository->find($destinationId) : null;

        // Point d'entree/sortie force explicitement dans l'autocompletion (ex: "Nation (RER)")
        // plutot que "tous modes" : voir TrajetFinder::trouverPlusCourtChemin.
        $origineMode = $this->modeValide($request->query->get('origineMode'));
        $destinationMode = $this->modeValide($request->query->get('destinationMode'));

        $resultat = null;
        $carte = null;
        $erreur = null;

        if (null !== $stationOrigine && null !== $stationDestination) {
            $resultat = $trajetFinder->trouverPlusCourtChemin(
                $stationOrigine->getId(),
                $stationDestination->getId(),
                $modesSelectionnes,
                $origineMode,
                $destinationMode,
            );

            if (null === $resultat) {
                $erreur = 'Aucun trajet trouvé entre ces deux stations.';
            } else {
                $carte = [
                    'reseau' => $this->construireReseauPourAffichage($tronconRepository),
                    'trajet' => $this->construireTrajetPourAffichage($resultat->etapes),
                ];
            }
        }

        $segments = null !== $resultat ? $this->construireSegmentsPourAffichage($resultat->etapes) : [];

        return $this->render('trajet/index.html.twig', [
            'stationOrigine' => $stationOrigine,
            'stationDestination' => $stationDestination,
            'origineMode' => $origineMode,
            'destinationMode' => $destinationMode,
            'modesSelectionnes' => $modesSelectionnes,
            'resultat' => $resultat,
            'segments' => $segments,
            'resumeSimple' => null !== $resultat ? $this->construireResumeSimple($segments) : null,
            'erreur' => $erreur,
            'carteJson' => null !== $carte ? json_encode($carte, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    private function modeValide(?string $mode): ?string
    {
        return \in_array($mode, self::MODES_DISPONIBLES, true) ? $mode : null;
    }

    /**
     * Recherche de stations pour l'autocompletion du formulaire (une entree par lieu reel,
     * jamais une par ligne/desserte — voir StationRepository::rechercherParLabel). Chaque
     * resultat porte la liste des modes qui la desservent : quand une station a plusieurs modes
     * (ex: Nation en Metro + RER), le JS propose en plus un point d'entree precis par mode (voir
     * TrajetFinder::trouverPlusCourtChemin, $modeEntreeOrigine/$modeEntreeDestination).
     */
    #[Route('/recherche-station', name: 'app_trajet_recherche_station', methods: ['GET'])]
    public function rechercheStation(Request $request, StationRepository $stationRepository, DesserteRepository $desserteRepository): JsonResponse
    {
        $recherche = trim((string) $request->query->get('q', ''));
        if ('' === $recherche) {
            return $this->json([]);
        }

        $stations = $stationRepository->rechercherParLabel($recherche);

        $dessertesParStation = [];
        foreach ($desserteRepository->findByStationIds(array_map(static fn (Station $s): int => $s->getId(), $stations)) as $desserte) {
            $stationId = $desserte->getStation()?->getId();
            if (null !== $stationId) {
                $dessertesParStation[$stationId][] = $desserte;
            }
        }

        $resultats = array_map(
            function (Station $station) use ($dessertesParStation) {
                $modes = [];
                foreach ($dessertesParStation[$station->getId()] ?? [] as $desserte) {
                    $mode = $desserte->getLigne()?->getModeFiltre();
                    if (null !== $mode && !\in_array($mode, $modes, true)) {
                        $modes[] = $mode;
                    }
                }
                usort($modes, static fn (string $a, string $b): int => array_search($a, self::MODES_DISPONIBLES, true) <=> array_search($b, self::MODES_DISPONIBLES, true));

                return [
                    'id' => $station->getId(),
                    'label' => $station->getLabel(),
                    'ville' => $station->getVille(),
                    'modes' => $modes,
                ];
            },
            $stations,
        );

        return $this->json($resultats);
    }

    /**
     * Regroupe les etapes consecutives de type "troncon" (meme ligne, sans changement) en un
     * seul segment affichant la chaine complete des stations traversees, plutot qu'une ligne
     * par troncon individuel. Les correspondances restent des segments a part.
     *
     * @param Etape[] $etapes
     * @return list<array{type: string, dessertes?: Desserte[], depart?: Desserte, arrivee?: Desserte, duree: float}>
     */
    private function construireSegmentsPourAffichage(array $etapes): array
    {
        $segments = [];
        $tronconCourant = null;

        foreach ($etapes as $etape) {
            if (Etape::TYPE_CORRESPONDANCE === $etape->type) {
                if (null !== $tronconCourant) {
                    $segments[] = $tronconCourant;
                    $tronconCourant = null;
                }

                $segments[] = [
                    'type' => Etape::TYPE_CORRESPONDANCE,
                    'depart' => $etape->depart,
                    'arrivee' => $etape->arrivee,
                    'duree' => $etape->dureeMinutes,
                ];

                continue;
            }

            if (null === $tronconCourant) {
                $tronconCourant = [
                    'type' => Etape::TYPE_TRONCON,
                    'dessertes' => [$etape->depart, $etape->arrivee],
                    'duree' => $etape->dureeMinutes,
                ];
            } else {
                $tronconCourant['dessertes'][] = $etape->arrivee;
                $tronconCourant['duree'] += $etape->dureeMinutes;
            }
        }

        if (null !== $tronconCourant) {
            $segments[] = $tronconCourant;
        }

        return $segments;
    }

    /**
     * Vue compacte du trajet : uniquement les stations de correspondance et les terminus
     * (pas les arrets intermediaires), pour un apercu rapide "ligne 7 -> 10 -> 12" plutot
     * que la liste complete de chaque station traversee (voir "segments"/vue detaillee).
     *
     * @param list<array{type: string, dessertes?: Desserte[], depart?: Desserte, arrivee?: Desserte, duree: float}> $segments
     * @return array{lignes: list<array{label: string, couleur: string}>, stations: list<array{id: ?int, label: string}>, segments: list<array{ligne: string, couleur: string, depart: array{id: ?int, label: string}, arrivee: array{id: ?int, label: string}}>}
     */
    private function construireResumeSimple(array $segments): array
    {
        $segmentsTroncon = array_values(array_filter($segments, static fn (array $s): bool => Etape::TYPE_TRONCON === $s['type']));

        $lignes = [];
        $stations = [];
        $segmentsSimples = [];

        foreach ($segmentsTroncon as $index => $segment) {
            $premiereDesserte = $segment['dessertes'][0];
            $derniereDesserte = $segment['dessertes'][array_key_last($segment['dessertes'])];
            $ligne = $premiereDesserte->getLigne();
            $couleur = '#' . ltrim($ligne?->getCouleur() ?? '6c757d', '#');

            $lignes[] = ['label' => $ligne?->getLabel() ?? '?', 'couleur' => $couleur];

            if (0 === $index) {
                $stations[] = $this->stationPourResume($premiereDesserte);
            }
            $stations[] = $this->stationPourResume($derniereDesserte);

            $segmentsSimples[] = [
                'ligne' => $ligne?->getLabel() ?? '?',
                'couleur' => $couleur,
                'depart' => $this->stationPourResume($premiereDesserte),
                'arrivee' => $this->stationPourResume($derniereDesserte),
            ];
        }

        return ['lignes' => $lignes, 'stations' => $stations, 'segments' => $segmentsSimples];
    }

    /**
     * @return array{id: ?int, label: string}
     */
    private function stationPourResume(Desserte $desserte): array
    {
        $station = $desserte->getStation();

        return ['id' => $station?->getId(), 'label' => $station?->getLabel() ?? '?'];
    }

    /**
     * Le reseau complet des troncons, positionnes sur le plan schematique officiel IDFM
     * (Station::schemaX/Y, voir app:importer-coordonnees-schema). Sert de fond de carte
     * (attenue) pour situer le trajet trouve dans son contexte. Les troncons dont une des deux
     * stations n'a pas de coordonnees connues sont ignores (~2% du reseau, cf commande import).
     *
     * @return list<array{x1: float, y1: float, x2: float, y2: float, couleur: string}>
     */
    private function construireReseauPourAffichage(TronconRepository $tronconRepository): array
    {
        $troncons = [];
        $vus = [];

        foreach ($tronconRepository->findAllWithDetails() as $troncon) {
            /** @var Troncon $troncon */
            foreach ($troncon->getSensCirculation() as $sens) {
                $depart = $sens['depart'];
                $arrivee = $sens['arrivee'];
                if (null === $depart || null === $arrivee) {
                    continue;
                }

                $stationDepart = $depart->getStation();
                $stationArrivee = $arrivee->getStation();
                if (null === $stationDepart || null === $stationArrivee) {
                    continue;
                }
                if (null === $stationDepart->getSchemaX() || null === $stationArrivee->getSchemaX()) {
                    continue;
                }

                $couleur = $depart->getLigne()?->getCouleur() ?? '6c757d';
                $cle = min($stationDepart->getId(), $stationArrivee->getId())
                    . '-' . max($stationDepart->getId(), $stationArrivee->getId())
                    . '-' . $couleur;
                if (isset($vus[$cle])) {
                    continue;
                }
                $vus[$cle] = true;

                $troncons[] = [
                    'x1' => $stationDepart->getSchemaX(),
                    'y1' => $stationDepart->getSchemaY(),
                    'x2' => $stationArrivee->getSchemaX(),
                    'y2' => $stationArrivee->getSchemaY(),
                    'couleur' => '#' . ltrim($couleur, '#'),
                ];
            }
        }

        return $troncons;
    }

    /**
     * Les etapes du trajet trouve, avec les coordonnees schematiques de chaque station
     * (etapes dont une station n'a pas de coordonnees connues sont ignorees : le trajet textuel
     * reste complet, seule cette representation graphique en manquera un morceau).
     *
     * @param Etape[] $etapes
     * @return list<array{labelDepart: string, x1: float, y1: float, labelArrivee: string, x2: float, y2: float, couleur: string, type: string}>
     */
    private function construireTrajetPourAffichage(array $etapes): array
    {
        $resultat = [];

        foreach ($etapes as $etape) {
            $stationDepart = $etape->depart->getStation();
            $stationArrivee = $etape->arrivee->getStation();
            if (null === $stationDepart || null === $stationArrivee) {
                continue;
            }
            if (null === $stationDepart->getSchemaX() || null === $stationArrivee->getSchemaX()) {
                continue;
            }

            $couleur = Etape::TYPE_CORRESPONDANCE === $etape->type
                ? '#dc3545'
                : '#' . ltrim($etape->depart->getLigne()?->getCouleur() ?? '6c757d', '#');

            $resultat[] = [
                'labelDepart' => $stationDepart->getLabel(),
                'x1' => $stationDepart->getSchemaX(),
                'y1' => $stationDepart->getSchemaY(),
                'labelArrivee' => $stationArrivee->getLabel(),
                'x2' => $stationArrivee->getSchemaX(),
                'y2' => $stationArrivee->getSchemaY(),
                'couleur' => $couleur,
                'type' => $etape->type,
            ];
        }

        return $resultat;
    }
}
