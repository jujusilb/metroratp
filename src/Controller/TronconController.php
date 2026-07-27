<?php

namespace App\Controller;

use App\Entity\Troncon;
use App\Form\TronconType;
use App\Repository\TronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/troncon')]
final class TronconController extends AbstractController
{
    #[Route(name: 'app_troncon_index', methods: ['GET'])]
    public function index(TronconRepository $tronconRepository): Response
    {
        return $this->render('troncon/index.html.twig', [
            'troncons' => $tronconRepository->findAllWithDetails(),
        ]);
    }

    #[Route('/new', name: 'app_troncon_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $troncon = new Troncon();
        $form = $this->createForm(TronconType::class, $troncon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($troncon);
            $entityManager->flush();

            return $this->redirectToRoute('app_troncon_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('troncon/new.html.twig', [
            'troncon' => $troncon,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_troncon_show', methods: ['GET'])]
    public function show(Troncon $troncon): Response
    {
        return $this->render('troncon/show.html.twig', [
            'troncon' => $troncon,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_troncon_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Troncon $troncon, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TronconType::class, $troncon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_troncon_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('troncon/edit.html.twig', [
            'troncon' => $troncon,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_troncon_delete', methods: ['POST'])]
    public function delete(Request $request, Troncon $troncon, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$troncon->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($troncon);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_troncon_index', [], Response::HTTP_SEE_OTHER);
    }
}
