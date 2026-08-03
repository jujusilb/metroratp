<?php

namespace App\Controller;

use App\Entity\Desserte;
use App\Repository\DesserteRepository;
use App\Service\Trajet\Etape;
use App\Service\TrajetFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/trajet')]
final class TrajetController extends AbstractController
{
    #[Route(name: 'app_trajet_index', methods: ['GET'])]
    public function index(Request $request, DesserteRepository $desserteRepository, TrajetFinder $trajetFinder): Response
    {
        $dessertes = [];
        foreach ($desserteRepository->findAllWithDetails() as $desserte) {
            $dessertes[$desserte->getId()] = $desserte;
        }

        $origineId = $request->query->getInt('origine') ?: null;
        $destinationId = $request->query->getInt('destination') ?: null;

        $resultat = null;
        $graphe = null;
        $erreur = null;

        if (null !== $origineId && null !== $destinationId) {
            $resultat = $trajetFinder->trouverPlusCourtChemin($origineId, $destinationId);

            if (null === $resultat) {
                $erreur = 'Aucun trajet trouvé entre ces deux dessertes.';
            } else {
                $graphe = $this->construireGraphePourAffichage($resultat->etapes, $dessertes[$origineId] ?? null);
            }
        }

        return $this->render('trajet/index.html.twig', [
            'dessertes' => $dessertes,
            'origineId' => $origineId,
            'destinationId' => $destinationId,
            'resultat' => $resultat,
            'segments' => null !== $resultat ? $this->construireSegmentsPourAffichage($resultat->etapes) : [],
            'erreur' => $erreur,
            'grapheJson' => null !== $graphe ? json_encode($graphe, JSON_THROW_ON_ERROR) : null,
        ]);
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
     * Construit les donnees noeuds/aretes attendues par vis-network, pour le chemin trouve
     * uniquement (pas tout le reseau : illisible et inutile pour un seul trajet).
     *
     * @param Etape[] $etapes
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function construireGraphePourAffichage(array $etapes, ?Desserte $origine): array
    {
        $nodes = [];
        $edges = [];

        $ajouterNode = function (Desserte $desserte) use (&$nodes): void {
            $id = $desserte->getId();
            if (isset($nodes[$id])) {
                return;
            }

            $nodes[$id] = [
                'id' => $id,
                'label' => sprintf(
                    "%s\n(%s)",
                    $desserte->getStation()?->getLabel() ?? '?',
                    $desserte->getLigne()?->getLabel() ?? '?',
                ),
                'color' => $desserte->getLigne()?->getCouleur() ?? '#6c757d',
            ];
        };

        if (null !== $origine) {
            $ajouterNode($origine);
        }

        foreach ($etapes as $etape) {
            $ajouterNode($etape->depart);
            $ajouterNode($etape->arrivee);

            $edges[] = [
                'from' => $etape->depart->getId(),
                'to' => $etape->arrivee->getId(),
                'label' => sprintf('%s min', round($etape->dureeMinutes, 1)),
                'dashes' => Etape::TYPE_CORRESPONDANCE === $etape->type,
                'color' => Etape::TYPE_CORRESPONDANCE === $etape->type ? '#dc3545' : '#495057',
            ];
        }

        return ['nodes' => array_values($nodes), 'edges' => $edges];
    }
}
