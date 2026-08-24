<?php

namespace App\Controller;

use App\Entity\Station;
use App\Form\StationType;
use App\Repository\StationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/station')]
final class StationController extends AbstractController
{
    #[Route(name: 'app_station_index', methods: ['GET'])]
    public function index(Request $request, StationRepository $stationRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $stationRepository->createQueryBuilder('s')->orderBy('s.label', 'ASC');
        $stationRepository->appliquerFiltreAlphabetEtRecherche($qb, 's.label', $lettre, $recherche);

        return $this->render('station/index.html.twig', [
            'stations' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_station_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $station = new Station();
        $form = $this->createForm(StationType::class, $station);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($station);
            $entityManager->flush();

            return $this->redirectToRoute('app_station_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('station/new.html.twig', [
            'station' => $station,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_station_show', methods: ['GET'])]
    public function show(Station $station): Response
    {
        return $this->render('station/show.html.twig', [
            'station' => $station,
            'carteAccesJson' => json_encode($this->construireCarteAcces($station), JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Pour la mini-carte des accès sur la fiche Station (plan de quartier "fait maison" - voir
     * carte-acces.js) : la position de la Station elle-même et celle de chaque Acces connu (les
     * accès sans coordonnées, dataset incomplet, sont simplement omis).
     *
     * @return array{stationLat: ?float, stationLon: ?float, acces: list<array{label: string, numero: ?string, lat: float, lon: float}>}
     */
    private function construireCarteAcces(Station $station): array
    {
        $acces = [];
        foreach ($station->getSorties() as $sortie) {
            $unAcces = $sortie->getAcces();
            if (null === $unAcces || null === $unAcces->getLatitude() || null === $unAcces->getLongitude()) {
                continue;
            }
            $acces[] = [
                'label' => $unAcces->getLabel(),
                'numero' => $unAcces->getNumero(),
                'lat' => $unAcces->getLatitude(),
                'lon' => $unAcces->getLongitude(),
            ];
        }

        return [
            'stationLat' => $station->getLatitude(),
            'stationLon' => $station->getLongitude(),
            'acces' => $acces,
        ];
    }

    #[Route('/{id}/edit', name: 'app_station_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Station $station, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StationType::class, $station);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_station_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('station/edit.html.twig', [
            'station' => $station,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_station_delete', methods: ['POST'])]
    public function delete(Request $request, Station $station, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$station->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($station);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_station_index', [], Response::HTTP_SEE_OTHER);
    }
}
