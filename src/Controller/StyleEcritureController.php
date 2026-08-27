<?php

namespace App\Controller;

use App\Entity\StyleEcriture;
use App\Form\StyleEcritureType;
use App\Repository\StyleEcritureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/style/ecriture')]
final class StyleEcritureController extends AbstractController
{
    #[Route(name: 'app_style_ecriture_index', methods: ['GET'])]
    public function index(Request $request, StyleEcritureRepository $styleEcritureRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $styleEcritureRepository->createQueryBuilder('s')->orderBy('s.label', 'ASC');
        $styleEcritureRepository->appliquerFiltreAlphabetEtRecherche($qb, 's.label', $lettre, $recherche);

        return $this->render('style_ecriture/index.html.twig', [
            'style_ecritures' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_style_ecriture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $styleEcriture = new StyleEcriture();
        $form = $this->createForm(StyleEcritureType::class, $styleEcriture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($styleEcriture);
            $entityManager->flush();

            return $this->redirectToRoute('app_style_ecriture_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('style_ecriture/new.html.twig', [
            'style_ecriture' => $styleEcriture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_style_ecriture_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(StyleEcriture $styleEcriture): Response
    {
        return $this->render('style_ecriture/show.html.twig', [
            'style_ecriture' => $styleEcriture,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_style_ecriture_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, StyleEcriture $styleEcriture, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StyleEcritureType::class, $styleEcriture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_style_ecriture_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('style_ecriture/edit.html.twig', [
            'style_ecriture' => $styleEcriture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_style_ecriture_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, StyleEcriture $styleEcriture, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$styleEcriture->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($styleEcriture);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_style_ecriture_index', [], Response::HTTP_SEE_OTHER);
    }
}
