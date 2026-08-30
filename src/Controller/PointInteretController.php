<?php

namespace App\Controller;

use App\Entity\PointInteret;
use App\Form\PointInteretType;
use App\Repository\PointInteretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/point-interet')]
final class PointInteretController extends AbstractController
{
    #[Route(name: 'app_point_interet_index', methods: ['GET'])]
    public function index(Request $request, PointInteretRepository $pointInteretRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $pointInteretRepository->createQueryBuilder('p')->orderBy('p.label', 'ASC');
        $pointInteretRepository->appliquerFiltreAlphabetEtRecherche($qb, 'p.label', $lettre, $recherche);

        return $this->render('point_interet/index.html.twig', [
            'point_interets' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_point_interet_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $pointInteret = new PointInteret();
        $form = $this->createForm(PointInteretType::class, $pointInteret);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($pointInteret);
            $entityManager->flush();

            return $this->redirectToRoute('app_point_interet_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('point_interet/new.html.twig', [
            'point_interet' => $pointInteret,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_point_interet_show', methods: ['GET'])]
    public function show(PointInteret $pointInteret): Response
    {
        return $this->render('point_interet/show.html.twig', [
            'point_interet' => $pointInteret,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_point_interet_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PointInteret $pointInteret, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PointInteretType::class, $pointInteret);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_point_interet_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('point_interet/edit.html.twig', [
            'point_interet' => $pointInteret,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_point_interet_delete', methods: ['POST'])]
    public function delete(Request $request, PointInteret $pointInteret, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$pointInteret->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($pointInteret);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_point_interet_index', [], Response::HTTP_SEE_OTHER);
    }
}
