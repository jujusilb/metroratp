<?php

namespace App\Controller;

use App\Entity\StatutTache;
use App\Form\StatutTacheType;
use App\Repository\StatutTacheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/statut-tache')]
final class StatutTacheController extends AbstractController
{
    #[Route(name: 'app_statut_tache_index', methods: ['GET'])]
    public function index(Request $request, StatutTacheRepository $statutTacheRepository, PaginatorInterface $paginator): Response
    {
        $qb = $statutTacheRepository->createQueryBuilder('s')->orderBy('s.label', 'ASC');

        return $this->render('statut_tache/index.html.twig', [
            'statut_taches' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_statut_tache_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $statutTache = new StatutTache();
        $form = $this->createForm(StatutTacheType::class, $statutTache);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($statutTache);
            $entityManager->flush();

            return $this->redirectToRoute('app_statut_tache_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('statut_tache/new.html.twig', [
            'statut_tache' => $statutTache,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_statut_tache_show', methods: ['GET'])]
    public function show(StatutTache $statutTache): Response
    {
        return $this->render('statut_tache/show.html.twig', [
            'statut_tache' => $statutTache,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_statut_tache_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, StatutTache $statutTache, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StatutTacheType::class, $statutTache);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_statut_tache_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('statut_tache/edit.html.twig', [
            'statut_tache' => $statutTache,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_statut_tache_delete', methods: ['POST'])]
    public function delete(Request $request, StatutTache $statutTache, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$statutTache->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($statutTache);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_statut_tache_index', [], Response::HTTP_SEE_OTHER);
    }
}
