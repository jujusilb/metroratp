<?php

namespace App\Controller;

use App\Entity\DepotLigne;
use App\Form\DepotLigneType;
use App\Repository\DepotLigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/depot/ligne')]
final class DepotLigneController extends AbstractController
{
    #[Route(name: 'app_depot_ligne_index', methods: ['GET'])]
    public function index(Request $request, DepotLigneRepository $depotLigneRepository, PaginatorInterface $paginator): Response
    {
        $qb = $depotLigneRepository->createQueryBuilder('dl')
            ->leftJoin('dl.depot', 'depot')->addSelect('depot')
            ->leftJoin('dl.ligne', 'ligne')->addSelect('ligne')
        ;

        return $this->render('depot_ligne/index.html.twig', [
            'depot_lignes' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_depot_ligne_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $depotLigne = new DepotLigne();
        $form = $this->createForm(DepotLigneType::class, $depotLigne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($depotLigne);
            $entityManager->flush();

            return $this->redirectToRoute('app_depot_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('depot_ligne/new.html.twig', [
            'depot_ligne' => $depotLigne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_depot_ligne_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DepotLigne $depotLigne): Response
    {
        return $this->render('depot_ligne/show.html.twig', [
            'depot_ligne' => $depotLigne,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_depot_ligne_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DepotLigne $depotLigne, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DepotLigneType::class, $depotLigne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_depot_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('depot_ligne/edit.html.twig', [
            'depot_ligne' => $depotLigne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_depot_ligne_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DepotLigne $depotLigne, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$depotLigne->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($depotLigne);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_depot_ligne_index', [], Response::HTTP_SEE_OTHER);
    }
}
