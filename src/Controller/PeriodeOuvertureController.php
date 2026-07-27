<?php

namespace App\Controller;

use App\Entity\PeriodeOuverture;
use App\Form\PeriodeOuvertureType;
use App\Repository\DesserteRepository;
use App\Repository\PeriodeOuvertureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/periode/ouverture')]
final class PeriodeOuvertureController extends AbstractController
{
    #[Route(name: 'app_periode_ouverture_index', methods: ['GET'])]
    public function index(PeriodeOuvertureRepository $periodeOuvertureRepository): Response
    {
        return $this->render('periode_ouverture/index.html.twig', [
            'periode_ouvertures' => $periodeOuvertureRepository->findAllWithDetails(),
        ]);
    }

    #[Route('/new', name: 'app_periode_ouverture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, DesserteRepository $desserteRepository): Response
    {
        $periodeOuverture = new PeriodeOuverture();

        // Pre-remplissage pratique quand on arrive depuis la fiche d'une desserte : la desserte
        // elle-meme, et l'ordre suivant (nombre de periodes deja enregistrees + 1).
        $desserteId = $request->query->getInt('desserte');
        if ($desserteId > 0) {
            $desserte = $desserteRepository->find($desserteId);
            if (null !== $desserte) {
                $periodeOuverture->setDesserte($desserte);
                $periodeOuverture->setOrdre(count($desserte->getPeriodesOuverture()) + 1);
            }
        }

        $form = $this->createForm(PeriodeOuvertureType::class, $periodeOuverture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($periodeOuverture);
            $entityManager->flush();

            // La desserte est obligatoire : on revient toujours sur sa fiche, ou la nouvelle
            // periode apparait desormais dans la liste.
            return $this->redirectToRoute('app_desserte_show', ['id' => $periodeOuverture->getDesserte()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('periode_ouverture/new.html.twig', [
            'periode_ouverture' => $periodeOuverture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_periode_ouverture_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(PeriodeOuverture $periodeOuverture): Response
    {
        return $this->render('periode_ouverture/show.html.twig', [
            'periode_ouverture' => $periodeOuverture,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_periode_ouverture_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, PeriodeOuverture $periodeOuverture, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PeriodeOuvertureType::class, $periodeOuverture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_periode_ouverture_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('periode_ouverture/edit.html.twig', [
            'periode_ouverture' => $periodeOuverture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_periode_ouverture_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, PeriodeOuverture $periodeOuverture, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$periodeOuverture->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($periodeOuverture);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_periode_ouverture_index', [], Response::HTTP_SEE_OTHER);
    }
}
