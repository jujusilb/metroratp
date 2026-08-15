<?php

namespace App\Controller;

use App\Entity\FontaineEau;
use App\Form\FontaineEauType;
use App\Repository\FontaineEauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fontaine-eau')]
final class FontaineEauController extends AbstractController
{
    #[Route(name: 'app_fontaine_eau_index', methods: ['GET'])]
    public function index(Request $request, FontaineEauRepository $fontaineEauRepository, PaginatorInterface $paginator): Response
    {
        $qb = $fontaineEauRepository->createQueryBuilder('f')->orderBy('f.label', 'ASC');

        return $this->render('fontaine_eau/index.html.twig', [
            'fontaines_eau' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_fontaine_eau_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $fontaineEau = new FontaineEau();
        $form = $this->createForm(FontaineEauType::class, $fontaineEau);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($fontaineEau);
            $entityManager->flush();

            return $this->redirectToRoute('app_fontaine_eau_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fontaine_eau/new.html.twig', [
            'fontaine_eau' => $fontaineEau,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_fontaine_eau_show', methods: ['GET'])]
    public function show(FontaineEau $fontaineEau): Response
    {
        return $this->render('fontaine_eau/show.html.twig', [
            'fontaine_eau' => $fontaineEau,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_fontaine_eau_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FontaineEau $fontaineEau, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FontaineEauType::class, $fontaineEau);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_fontaine_eau_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fontaine_eau/edit.html.twig', [
            'fontaine_eau' => $fontaineEau,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_fontaine_eau_delete', methods: ['POST'])]
    public function delete(Request $request, FontaineEau $fontaineEau, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$fontaineEau->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($fontaineEau);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_fontaine_eau_index', [], Response::HTTP_SEE_OTHER);
    }
}
