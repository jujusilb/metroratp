<?php

namespace App\Controller;

use App\Entity\TypeTroncon;
use App\Form\TypeTronconType;
use App\Repository\TypeTronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/type/troncon')]
final class TypeTronconController extends AbstractController
{
    #[Route(name: 'app_type_troncon_index', methods: ['GET'])]
    public function index(Request $request, TypeTronconRepository $typeTronconRepository, PaginatorInterface $paginator): Response
    {
        $qb = $typeTronconRepository->createQueryBuilder('t')->orderBy('t.label', 'ASC');

        return $this->render('type_troncon/index.html.twig', [
            'type_troncons' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_type_troncon_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $typeTroncon = new TypeTroncon();
        $form = $this->createForm(TypeTronconType::class, $typeTroncon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($typeTroncon);
            $entityManager->flush();

            return $this->redirectToRoute('app_type_troncon_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type_troncon/new.html.twig', [
            'type_troncon' => $typeTroncon,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_type_troncon_show', methods: ['GET'])]
    public function show(int $id, TypeTronconRepository $typeTronconRepository): Response
    {
        $typeTroncon = $typeTronconRepository->findWithTronconsDetails($id) ?? throw $this->createNotFoundException();

        return $this->render('type_troncon/show.html.twig', [
            'type_troncon' => $typeTroncon,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_troncon_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TypeTroncon $typeTroncon, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TypeTronconType::class, $typeTroncon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_type_troncon_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type_troncon/edit.html.twig', [
            'type_troncon' => $typeTroncon,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_type_troncon_delete', methods: ['POST'])]
    public function delete(Request $request, TypeTroncon $typeTroncon, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$typeTroncon->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($typeTroncon);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_type_troncon_index', [], Response::HTTP_SEE_OTHER);
    }
}
