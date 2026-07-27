<?php

namespace App\Controller;

use App\Entity\StyleStation;
use App\Form\StyleStationType;
use App\Repository\StyleStationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/style/station')]
final class StyleStationController extends AbstractController
{
    #[Route(name: 'app_style_station_index', methods: ['GET'])]
    public function index(StyleStationRepository $styleStationRepository): Response
    {
        return $this->render('style_station/index.html.twig', [
            'style_stations' => $styleStationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_style_station_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $styleStation = new StyleStation();
        $form = $this->createForm(StyleStationType::class, $styleStation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($styleStation);
            $entityManager->flush();

            return $this->redirectToRoute('app_style_station_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('style_station/new.html.twig', [
            'style_station' => $styleStation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_style_station_show', methods: ['GET'])]
    public function show(StyleStation $styleStation): Response
    {
        return $this->render('style_station/show.html.twig', [
            'style_station' => $styleStation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_style_station_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, StyleStation $styleStation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StyleStationType::class, $styleStation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_style_station_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('style_station/edit.html.twig', [
            'style_station' => $styleStation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_style_station_delete', methods: ['POST'])]
    public function delete(Request $request, StyleStation $styleStation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$styleStation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($styleStation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_style_station_index', [], Response::HTTP_SEE_OTHER);
    }
}
