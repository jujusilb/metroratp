<?php

namespace App\Controller;

use App\Entity\Ligne;
use App\Entity\Mission;
use App\Form\MissionType;
use App\Repository\LigneRepository;
use App\Repository\MissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mission')]
final class MissionController extends AbstractController
{
    #[Route(name: 'app_mission_index', methods: ['GET'])]
    public function index(MissionRepository $missionRepository): Response
    {
        return $this->render('mission/choix_ligne.html.twig', [
            'lignes' => $missionRepository->findLignesWithMissions(),
        ]);
    }

    #[Route('/ligne/{id}', name: 'app_mission_choix_direction', methods: ['GET'])]
    public function choixDirection(Ligne $ligne, MissionRepository $missionRepository): Response
    {
        return $this->render('mission/choix_direction.html.twig', [
            'ligne' => $ligne,
            'directions' => $missionRepository->findDirectionsForLigne($ligne->getId()),
        ]);
    }

    #[Route('/ligne/{ligneId}/direction/{directionId}', name: 'app_mission_choix_service', methods: ['GET'])]
    public function choixService(
        int $ligneId,
        int $directionId,
        LigneRepository $ligneRepository,
        MissionRepository $missionRepository,
    ): Response {
        $ligne = $ligneRepository->find($ligneId) ?? throw $this->createNotFoundException();
        $services = $missionRepository->findServicesForDirection($ligneId, $directionId);

        // Une seule direction possible pour ce service (ligne sans embranchement) : pas besoin
        // de faire choisir, on va directement au trajet.
        if (1 === count($services)) {
            return $this->redirectToRoute('app_mission_trajet', [
                'ligneId' => $ligneId,
                'directionId' => $directionId,
                'serviceId' => $services[0]->getId(),
            ]);
        }

        return $this->render('mission/choix_service.html.twig', [
            'ligne' => $ligne,
            'directionId' => $directionId,
            'services' => $services,
        ]);
    }

    #[Route('/ligne/{ligneId}/direction/{directionId}/service/{serviceId}', name: 'app_mission_trajet', methods: ['GET'])]
    public function trajet(
        int $ligneId,
        int $directionId,
        int $serviceId,
        LigneRepository $ligneRepository,
        MissionRepository $missionRepository,
    ): Response {
        $ligne = $ligneRepository->find($ligneId) ?? throw $this->createNotFoundException();
        $missions = $missionRepository->findJourney($ligneId, $directionId, $serviceId);

        return $this->render('mission/trajet.html.twig', [
            'ligne' => $ligne,
            'missions' => $missions,
        ]);
    }

    #[Route('/new', name: 'app_mission_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $mission = new Mission();
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($mission);
            $entityManager->flush();

            return $this->redirectToRoute('app_mission_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('mission/new.html.twig', [
            'mission' => $mission,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_mission_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Mission $mission): Response
    {
        return $this->render('mission/show.html.twig', [
            'mission' => $mission,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_mission_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Mission $mission, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_mission_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('mission/edit.html.twig', [
            'mission' => $mission,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_mission_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Mission $mission, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$mission->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($mission);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_mission_index', [], Response::HTTP_SEE_OTHER);
    }
}
