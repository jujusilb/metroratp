<?php

namespace App\Controller;

use App\Entity\Raison;
use App\Form\RaisonType;
use App\Repository\RaisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/raison')]
final class RaisonController extends AbstractController
{
    #[Route(name: 'app_raison_index', methods: ['GET'])]
    public function index(Request $request, RaisonRepository $raisonRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $raisonRepository->createQueryBuilder('r')->orderBy('r.label', 'ASC');
        $raisonRepository->appliquerFiltreAlphabetEtRecherche($qb, 'r.label', $lettre, $recherche);

        return $this->render('raison/index.html.twig', [
            'raisons' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_raison_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $raison = new Raison();
        $form = $this->createForm(RaisonType::class, $raison);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($raison);
            $entityManager->flush();

            return $this->redirectToRoute('app_raison_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('raison/new.html.twig', [
            'raison' => $raison,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_raison_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Raison $raison): Response
    {
        return $this->render('raison/show.html.twig', [
            'raison' => $raison,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_raison_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Raison $raison, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RaisonType::class, $raison);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_raison_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('raison/edit.html.twig', [
            'raison' => $raison,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_raison_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Raison $raison, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$raison->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($raison);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_raison_index', [], Response::HTTP_SEE_OTHER);
    }
}
