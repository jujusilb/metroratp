<?php

namespace App\Controller;

use App\Entity\PoleEchange;
use App\Form\PoleEchangeType;
use App\Repository\PoleEchangeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/pole-echange')]
final class PoleEchangeController extends AbstractController
{
    #[Route(name: 'app_pole_echange_index', methods: ['GET'])]
    public function index(Request $request, PoleEchangeRepository $poleEchangeRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $poleEchangeRepository->createQueryBuilder('p')->orderBy('p.label', 'ASC');
        $poleEchangeRepository->appliquerFiltreAlphabetEtRecherche($qb, 'p.label', $lettre, $recherche);

        return $this->render('pole_echange/index.html.twig', [
            'pole_echanges' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_pole_echange_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $poleEchange = new PoleEchange();
        $form = $this->createForm(PoleEchangeType::class, $poleEchange);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($poleEchange);
            $entityManager->flush();

            return $this->redirectToRoute('app_pole_echange_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pole_echange/new.html.twig', [
            'pole_echange' => $poleEchange,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_pole_echange_show', methods: ['GET'])]
    public function show(PoleEchange $poleEchange): Response
    {
        return $this->render('pole_echange/show.html.twig', [
            'pole_echange' => $poleEchange,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_pole_echange_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PoleEchange $poleEchange, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PoleEchangeType::class, $poleEchange);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_pole_echange_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pole_echange/edit.html.twig', [
            'pole_echange' => $poleEchange,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_pole_echange_delete', methods: ['POST'])]
    public function delete(Request $request, PoleEchange $poleEchange, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$poleEchange->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($poleEchange);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_pole_echange_index', [], Response::HTTP_SEE_OTHER);
    }
}
