<?php

namespace App\Controller;

use App\Entity\MaterielDepot;
use App\Form\MaterielDepotType;
use App\Repository\MaterielDepotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/materiel/depot')]
final class MaterielDepotController extends AbstractController
{
    #[Route(name: 'app_materiel_depot_index', methods: ['GET'])]
    public function index(Request $request, MaterielDepotRepository $materielDepotRepository, PaginatorInterface $paginator): Response
    {
        $qb = $materielDepotRepository->createQueryBuilder('md')
            ->leftJoin('md.materiel', 'materiel')->addSelect('materiel')
            ->leftJoin('md.depot', 'depot')->addSelect('depot')
        ;

        return $this->render('materiel_depot/index.html.twig', [
            'materiel_depots' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_materiel_depot_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $materielDepot = new MaterielDepot();
        $form = $this->createForm(MaterielDepotType::class, $materielDepot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($materielDepot);
            $entityManager->flush();

            return $this->redirectToRoute('app_materiel_depot_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('materiel_depot/new.html.twig', [
            'materiel_depot' => $materielDepot,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_materiel_depot_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(MaterielDepot $materielDepot): Response
    {
        return $this->render('materiel_depot/show.html.twig', [
            'materiel_depot' => $materielDepot,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_materiel_depot_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, MaterielDepot $materielDepot, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MaterielDepotType::class, $materielDepot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_materiel_depot_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('materiel_depot/edit.html.twig', [
            'materiel_depot' => $materielDepot,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_materiel_depot_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, MaterielDepot $materielDepot, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$materielDepot->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($materielDepot);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_materiel_depot_index', [], Response::HTTP_SEE_OTHER);
    }
}
