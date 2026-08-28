<?php

namespace App\Controller;

use App\Entity\DepotGestionnaire;
use App\Form\DepotGestionnaireType;
use App\Repository\DepotGestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/depot/gestionnaire')]
final class DepotGestionnaireController extends AbstractController
{
    #[Route(name: 'app_depot_gestionnaire_index', methods: ['GET'])]
    public function index(Request $request, DepotGestionnaireRepository $depotGestionnaireRepository, PaginatorInterface $paginator): Response
    {
        $qb = $depotGestionnaireRepository->createQueryBuilder('dg')
            ->leftJoin('dg.depot', 'depot')->addSelect('depot')
            ->leftJoin('dg.gestionnaire', 'gestionnaire')->addSelect('gestionnaire')
        ;

        return $this->render('depot_gestionnaire/index.html.twig', [
            'depot_gestionnaires' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_depot_gestionnaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $depotGestionnaire = new DepotGestionnaire();
        $form = $this->createForm(DepotGestionnaireType::class, $depotGestionnaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($depotGestionnaire);
            $entityManager->flush();

            return $this->redirectToRoute('app_depot_gestionnaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('depot_gestionnaire/new.html.twig', [
            'depot_gestionnaire' => $depotGestionnaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_depot_gestionnaire_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DepotGestionnaire $depotGestionnaire): Response
    {
        return $this->render('depot_gestionnaire/show.html.twig', [
            'depot_gestionnaire' => $depotGestionnaire,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_depot_gestionnaire_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DepotGestionnaire $depotGestionnaire, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DepotGestionnaireType::class, $depotGestionnaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_depot_gestionnaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('depot_gestionnaire/edit.html.twig', [
            'depot_gestionnaire' => $depotGestionnaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_depot_gestionnaire_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DepotGestionnaire $depotGestionnaire, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$depotGestionnaire->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($depotGestionnaire);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_depot_gestionnaire_index', [], Response::HTTP_SEE_OTHER);
    }
}
