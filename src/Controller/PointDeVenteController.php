<?php

namespace App\Controller;

use App\Entity\PointDeVente;
use App\Form\PointDeVenteType;
use App\Repository\PointDeVenteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/point-de-vente')]
final class PointDeVenteController extends AbstractController
{
    #[Route(name: 'app_point_de_vente_index', methods: ['GET'])]
    public function index(Request $request, PointDeVenteRepository $pointDeVenteRepository, PaginatorInterface $paginator): Response
    {
        $qb = $pointDeVenteRepository->createQueryBuilder('p')
            ->leftJoin('p.station', 'station')->addSelect('station')
            ->orderBy('p.label', 'ASC')
        ;

        return $this->render('point_de_vente/index.html.twig', [
            'points_de_vente' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_point_de_vente_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $pointDeVente = new PointDeVente();
        $form = $this->createForm(PointDeVenteType::class, $pointDeVente);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($pointDeVente);
            $entityManager->flush();

            return $this->redirectToRoute('app_point_de_vente_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('point_de_vente/new.html.twig', [
            'point_de_vente' => $pointDeVente,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_point_de_vente_show', methods: ['GET'])]
    public function show(PointDeVente $pointDeVente): Response
    {
        return $this->render('point_de_vente/show.html.twig', [
            'point_de_vente' => $pointDeVente,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_point_de_vente_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PointDeVente $pointDeVente, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PointDeVenteType::class, $pointDeVente);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_point_de_vente_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('point_de_vente/edit.html.twig', [
            'point_de_vente' => $pointDeVente,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_point_de_vente_delete', methods: ['POST'])]
    public function delete(Request $request, PointDeVente $pointDeVente, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$pointDeVente->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($pointDeVente);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_point_de_vente_index', [], Response::HTTP_SEE_OTHER);
    }
}
