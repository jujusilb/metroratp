<?php

namespace App\Controller;

use App\Entity\Automatisation;
use App\Form\AutomatisationType;
use App\Repository\AutomatisationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/automatisation')]
final class AutomatisationController extends AbstractController
{
    #[Route(name: 'app_automatisation_index', methods: ['GET'])]
    public function index(Request $request, AutomatisationRepository $automatisationRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $automatisationRepository->createQueryBuilder('a')->orderBy('a.label', 'ASC');
        $automatisationRepository->appliquerFiltreAlphabetEtRecherche($qb, 'a.label', $lettre, $recherche);

        return $this->render('automatisation/index.html.twig', [
            'automatisations' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_automatisation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $automatisation = new Automatisation();
        $form = $this->createForm(AutomatisationType::class, $automatisation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($automatisation);
            $entityManager->flush();

            return $this->redirectToRoute('app_automatisation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('automatisation/new.html.twig', [
            'automatisation' => $automatisation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_automatisation_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Automatisation $automatisation): Response
    {
        return $this->render('automatisation/show.html.twig', [
            'automatisation' => $automatisation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_automatisation_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Automatisation $automatisation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AutomatisationType::class, $automatisation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_automatisation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('automatisation/edit.html.twig', [
            'automatisation' => $automatisation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_automatisation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Automatisation $automatisation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$automatisation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($automatisation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_automatisation_index', [], Response::HTTP_SEE_OTHER);
    }
}
