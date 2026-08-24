<?php

namespace App\Controller;

use App\Entity\Gestionnaire;
use App\Form\GestionnaireType;
use App\Repository\GestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gestionnaire')]
final class GestionnaireController extends AbstractController
{
    #[Route(name: 'app_gestionnaire_index', methods: ['GET'])]
    public function index(Request $request, GestionnaireRepository $gestionnaireRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $gestionnaireRepository->createQueryBuilder('g')->orderBy('g.label', 'ASC');
        $gestionnaireRepository->appliquerFiltreAlphabetEtRecherche($qb, 'g.label', $lettre, $recherche);

        return $this->render('gestionnaire/index.html.twig', [
            'gestionnaires' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_gestionnaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $gestionnaire = new Gestionnaire();
        $form = $this->createForm(GestionnaireType::class, $gestionnaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($gestionnaire);
            $entityManager->flush();

            return $this->redirectToRoute('app_gestionnaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gestionnaire/new.html.twig', [
            'gestionnaire' => $gestionnaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gestionnaire_show', methods: ['GET'])]
    public function show(Gestionnaire $gestionnaire): Response
    {
        return $this->render('gestionnaire/show.html.twig', [
            'gestionnaire' => $gestionnaire,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gestionnaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Gestionnaire $gestionnaire, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(GestionnaireType::class, $gestionnaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gestionnaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gestionnaire/edit.html.twig', [
            'gestionnaire' => $gestionnaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gestionnaire_delete', methods: ['POST'])]
    public function delete(Request $request, Gestionnaire $gestionnaire, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$gestionnaire->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($gestionnaire);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gestionnaire_index', [], Response::HTTP_SEE_OTHER);
    }
}
