<?php

namespace App\Controller;

use App\Entity\PositionRame;
use App\Form\PositionRameType;
use App\Repository\PositionRameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/position-rame')]
final class PositionRameController extends AbstractController
{
    #[Route(name: 'app_position_rame_index', methods: ['GET'])]
    public function index(PositionRameRepository $positionRameRepository): Response
    {
        return $this->render('position_rame/index.html.twig', [
            'position_rames' => $positionRameRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_position_rame_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $positionRame = new PositionRame();
        $form = $this->createForm(PositionRameType::class, $positionRame);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($positionRame);
            $entityManager->flush();

            return $this->redirectToRoute('app_position_rame_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('position_rame/new.html.twig', [
            'position_rame' => $positionRame,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_position_rame_show', methods: ['GET'])]
    public function show(PositionRame $positionRame): Response
    {
        return $this->render('position_rame/show.html.twig', [
            'position_rame' => $positionRame,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_position_rame_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PositionRame $positionRame, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PositionRameType::class, $positionRame);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_position_rame_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('position_rame/edit.html.twig', [
            'position_rame' => $positionRame,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_position_rame_delete', methods: ['POST'])]
    public function delete(Request $request, PositionRame $positionRame, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$positionRame->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($positionRame);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_position_rame_index', [], Response::HTTP_SEE_OTHER);
    }
}
