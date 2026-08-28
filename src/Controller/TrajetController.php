<?php

namespace App\Controller;

use App\Entity\Desserte;
use App\Entity\PositionRame;
use App\Entity\Station;
use App\Repository\DesserteRepository;
use App\Repository\PositionRameRepository;
use App\Repository\StationRepository;
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
    private const MODES_DISPONIBLES = ['metro', 'tram', 'rer', 'bus_ratp', 'bus_tiers', 'telepherique', 'funiculaire', 'train'];

    #[Route(name: 'app_trajet_index', methods: ['GET'])]
    public function index(Request $request, StationRepository $stationRepository, TrajetFinder $trajetFinder, PositionRameRepository $positionRameRepository): Response
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

        // Moment du depart (format natif de <input type="datetime-local">) : sert a exclure une
        // Ligne fermee a cet instant (Noctilien en pleine journee, etc. - voir HoraireLigne).
        // Defaut "maintenant" si absent/invalide, pour que le champ affiche une valeur exploitable
        // des le premier chargement de la page.
        $momentBrut = $request->query->get('moment');
        $moment = null !== $momentBrut ? \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $momentBrut) : false;
        if (false === $moment) {
            $moment = new \DateTimeImmutable();
        }

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
                $moment,
            );

            if (null === $resultat) {
                $erreur = 'Aucun trajet trouvé entre ces deux stations.';
            } else {
                $carte = [
                    'trajet' => $this->construireTrajetPourAffichage($resultat->etapes),
                    'tracesLignes' => $this->construireTracesLignesPourAffichage($resultat->etapes),
                    'stationsInfo' => $this->construireInfosStationsPourAffichage($resultat->etapes),
                ];
            }
        }

        $segments = null !== $resultat ? $this->construireSegmentsPourAffichage($resultat->etapes, $positionRameRepository) : [];

        return $this->render('trajet/index.html.twig', [
            'stationOrigine' => $stationOrigine,
            'stationDestination' => $stationDestination,
            'origineMode' => $origineMode,
            'destinationMode' => $destinationMode,
            'moment' => $moment,
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
     *
     * Filtre par les cases "Modes de transport" cochees (parametre modes[], meme logique par
     * defaut que index()) : si un mode est decoche, aucune station qui n'est desservie QUE par ce
     * mode ne doit apparaitre — la choisir serait un cul-de-sac garanti, puisque
     * TrajetFinder::dessertesIdsPourStation() l'exclurait de toute facon du calcul. Une station
     * desservie par plusieurs modes reste proposee, mais seuls les modes cochés apparaissent dans
     * ses sous-options.
     */
    #[Route('/recherche-station', name: 'app_trajet_recherche_station', methods: ['GET'])]
    public function rechercheStation(Request $request, StationRepository $stationRepository, DesserteRepository $desserteRepository): JsonResponse
    {
        $recherche = trim((string) $request->query->get('q', ''));
        if ('' === $recherche) {
            return $this->json([]);
        }

        $modesAutorises = $request->query->has('modes') ? $request->query->all('modes') : self::MODES_DISPONIBLES;

        $stations = $stationRepository->rechercherParLabel($recherche);

        $dessertesParStation = [];
        foreach ($desserteRepository->findByStationIds(array_map(static fn (Station $s): int => $s->getId(), $stations)) as $desserte) {
            $stationId = $desserte->getStation()?->getId();
            if (null !== $stationId) {
                $dessertesParStation[$stationId][] = $desserte;
            }
        }

        $resultats = [];
        foreach ($stations as $station) {
            $modes = [];
            foreach ($dessertesParStation[$station->getId()] ?? [] as $desserte) {
                $mode = $desserte->getLigne()?->getModeFiltre();
                if (null !== $mode && \in_array($mode, $modesAutorises, true) && !\in_array($mode, $modes, true)) {
                    $modes[] = $mode;
                }
            }

            if ([] === $modes) {
                continue; // aucun mode coche ne dessert cette station : cul-de-sac garanti
            }

            usort($modes, static fn (string $a, string $b): int => array_search($a, self::MODES_DISPONIBLES, true) <=> array_search($b, self::MODES_DISPONIBLES, true));

            $resultats[] = [
                'id' => $station->getId(),
                'label' => $station->getLabel(),
                'ville' => $station->getVille(),
                'modes' => $modes,
            ];
        }

        return $this->json($resultats);
    }

    /**
     * Regroupe les etapes consecutives de type "troncon" (meme ligne, sans changement) en un
     * seul segment affichant la chaine complete des stations traversees, plutot qu'une ligne
     * par troncon individuel. Les correspondances restent des segments a part.
     *
     * Chaque troncon porte aussi LE conseil de positionnement dans la rame (PositionRame) pour la
     * Ligne empruntee, a la Station ou ce troncon COMMENCE (l'embarquement) - c'est la qu'il est
     * actionnable, avant meme de monter dans la rame. Filtre par le sens de circulation reellement
     * emprunte (identifie par la 2e Desserte du troncon, premiere station reelle suivante) : sans
     * ce filtre, les 2 sens opposes d'une meme Station+Ligne se melangeaient (voir
     * documentation/TODO.md). Le sens est constant sur tout le troncon (un train ne rebrousse pas
     * chemin en cours de route), donc un seul conseil suffit pour l'ensemble du troncon.
     *
     * @param Etape[] $etapes
     * @return list<array{type: string, dessertes?: Desserte[], depart?: Desserte, arrivee?: Desserte, duree: float, positionRame?: ?PositionRame}>
     */
    private function construireSegmentsPourAffichage(array $etapes, PositionRameRepository $positionRameRepository): array
    {
        $segments = [];
        $tronconCourant = null;

        $terminerTroncon = function () use (&$tronconCourant, $positionRameRepository): void {
            if (null === $tronconCourant) {
                return;
            }
            $dessertes = $tronconCourant['dessertes'];
            $premiere = $dessertes[0];
            $suivante = $dessertes[1] ?? null;
            $ligne = $premiere->getLigne();
            $station = $premiere->getStation();
            $prochaineStation = $suivante?->getStation();
            if (null !== $ligne && null !== $station && null !== $prochaineStation) {
                $tronconCourant['positionRame'] = $positionRameRepository->trouverPourEmbarquement($station->getId(), $ligne->getId(), $prochaineStation->getId());
            }
        };

        foreach ($etapes as $etape) {
            if (Etape::TYPE_CORRESPONDANCE === $etape->type) {
                if (null !== $tronconCourant) {
                    $terminerTroncon();
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
            $terminerTroncon();
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
     * @return array{lignes: list<array{id: ?int, label: string, couleur: string}>, stations: list<array{id: ?int, label: string}>, segments: list<array{ligneId: ?int, ligne: string, couleur: string, depart: array{id: ?int, label: string}, arrivee: array{id: ?int, label: string}}>}
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

            $lignes[] = ['id' => $ligne?->getId(), 'label' => $ligne?->getLabel() ?? '?', 'couleur' => $couleur];

            if (0 === $index) {
                $stations[] = $this->stationPourResume($premiereDesserte);
            }
            $stations[] = $this->stationPourResume($derniereDesserte);

            $segmentsSimples[] = [
                'ligneId' => $ligne?->getId(),
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
     * Pour la bulle au survol de chaque station sur la carte du trajet (voir aussi
     * StationRepository::donneesPourCarteComplete() pour la carte complete du reseau, meme
     * format) : uniquement les (mode, ligne, gestionnaire) des stations REELLEMENT traversees par
     * ce trajet, pas l'ensemble de leurs dessertes reelles (afficher "cette station a aussi une
     * ligne 6" alors que le trajet ne l'emprunte pas serait trompeur ici). Cle = label de la
     * Station, comme la Map "stationsTrajet" cote JS (voir trajet-carte.js) - pas les coordonnees,
     * pour eviter tout risque de formatage different d'un flottant PHP vs JSON vs JS.
     *
     * @param Etape[] $etapes
     * @return array<string, list<array{mode: ?string, ligne: string, gestionnaire: ?string}>>
     */
    private function construireInfosStationsPourAffichage(array $etapes): array
    {
        $infos = [];

        $ajouter = function (Desserte $desserte) use (&$infos): void {
            $station = $desserte->getStation();
            $ligne = $desserte->getLigne();
            if (null === $station || null === $ligne || null === $station->getLatitude()) {
                return;
            }

            $cle = $station->getLabel();
            $gestionnaireLabel = $ligne->getGestionnaire()?->getLabel();
            // Dedoublonne par ligne (pas par Desserte) : plusieurs Desserte de la meme Ligne a la
            // meme Station (sens/direction differents) ne doivent apparaitre qu'une fois dans la
            // bulle - on garde la premiere rencontree pour le lien vers le detail.
            $cleLigne = $ligne->getId().'|'.($ligne->getLabel() ?? '?');

            $infos[$cle][$cleLigne] ??= [
                'mode' => $ligne->getTypeTransport()?->getLabel(),
                'ligne' => $ligne->getLabel() ?? '?',
                'ligneId' => $ligne->getId(),
                'gestionnaire' => 'RATP' !== $gestionnaireLabel ? $gestionnaireLabel : null,
                'desserteUrl' => $this->generateUrl('app_desserte_show', ['id' => $desserte->getId()]),
            ];
        };

        foreach ($etapes as $etape) {
            $ajouter($etape->depart);
            $ajouter($etape->arrivee);
        }

        return array_map(static fn (array $lignes): array => array_values($lignes), $infos);
    }

    /**
     * Les etapes du trajet trouve, avec les coordonnees geographiques reelles de chaque station
     * (etapes dont une station n'a pas de coordonnees connues sont ignorees : le trajet textuel
     * reste complet, seule cette representation graphique en manquera un morceau). Les etapes de
     * type troncon portent aussi l'id de la Ligne empruntee (voir construireTracesLignesPourAffichage) :
     * la carte s'en sert pour dessiner le trace reel (suit les rues/rails) plutot qu'une ligne
     * droite entre les deux stations, quand ce trace est connu.
     *
     * @param Etape[] $etapes
     * @return list<array{labelDepart: string, lat1: float, lon1: float, labelArrivee: string, lat2: float, lon2: float, couleur: string, type: string, ligneId: ?int}>
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
            if (null === $stationDepart->getLatitude() || null === $stationArrivee->getLatitude()) {
                continue;
            }

            $couleur = Etape::TYPE_CORRESPONDANCE === $etape->type
                ? '#dc3545'
                : '#' . ltrim($etape->depart->getLigne()?->getCouleur() ?? '6c757d', '#');

            $resultat[] = [
                'labelDepart' => $stationDepart->getLabel(),
                'lat1' => $stationDepart->getLatitude(),
                'lon1' => $stationDepart->getLongitude(),
                'labelArrivee' => $stationArrivee->getLabel(),
                'lat2' => $stationArrivee->getLatitude(),
                'lon2' => $stationArrivee->getLongitude(),
                'couleur' => $couleur,
                'type' => $etape->type,
                'ligneId' => Etape::TYPE_TRONCON === $etape->type ? $etape->depart->getLigne()?->getId() : null,
            ];
        }

        return $resultat;
    }

    /**
     * Le trace geometrique reel (Ligne::trace) de chaque Ligne empruntee par une etape de type
     * troncon du trajet trouve - seulement celles-la, jamais l'ensemble du reseau (les traces
     * peuvent etre volumineuses pour une ligne de bus longue).
     *
     * @param Etape[] $etapes
     * @return array<int, array> ligneId => trace (liste de lignes, chacune une liste de [lon, lat])
     */
    private function construireTracesLignesPourAffichage(array $etapes): array
    {
        $traces = [];

        foreach ($etapes as $etape) {
            if (Etape::TYPE_TRONCON !== $etape->type) {
                continue;
            }
            $ligne = $etape->depart->getLigne();
            if (null === $ligne || isset($traces[$ligne->getId()])) {
                continue;
            }
            $trace = $ligne->getTrace();
            if (null !== $trace) {
                $traces[$ligne->getId()] = $trace;
            }
        }

        return $traces;
    }
}
