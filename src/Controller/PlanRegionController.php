<?php

namespace App\Controller;

use App\Entity\PlanRegion;
use App\Form\PlanRegionType;
use App\Repository\PlanRegionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/plan-region')]
final class PlanRegionController extends AbstractController
{
    #[Route(name: 'app_plan_region_index', methods: ['GET'])]
    public function index(Request $request, PlanRegionRepository $planRegionRepository, PaginatorInterface $paginator): Response
    {
        $qb = $planRegionRepository->createQueryBuilder('p')->orderBy('p.ordre', 'ASC');

        return $this->render('plan_region/index.html.twig', [
            'plans_region' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_plan_region_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $planRegion = new PlanRegion();
        $form = $this->createForm(PlanRegionType::class, $planRegion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($planRegion);
            $entityManager->flush();

            return $this->redirectToRoute('app_plan_region_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('plan_region/new.html.twig', [
            'plan_region' => $planRegion,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_plan_region_show', methods: ['GET'])]
    public function show(PlanRegion $planRegion): Response
    {
        return $this->render('plan_region/show.html.twig', [
            'plan_region' => $planRegion,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plan_region_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PlanRegion $planRegion, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PlanRegionType::class, $planRegion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_plan_region_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('plan_region/edit.html.twig', [
            'plan_region' => $planRegion,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_plan_region_delete', methods: ['POST'])]
    public function delete(Request $request, PlanRegion $planRegion, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$planRegion->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($planRegion);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_plan_region_index', [], Response::HTTP_SEE_OTHER);
    }
}
