<?php

namespace App\Controller;

use App\Entity\ProjetArret;
use App\Form\ProjetArretType;
use App\Repository\ProjetArretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projet-arret')]
final class ProjetArretController extends AbstractController
{
    #[Route(name: 'app_projet_arret_index', methods: ['GET'])]
    public function index(Request $request, ProjetArretRepository $projetArretRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $projetArretRepository->createQueryBuilder('p')->orderBy('p.nomProjet', 'ASC');
        $projetArretRepository->appliquerFiltreAlphabetEtRecherche($qb, 'p.nomProjet', $lettre, $recherche);

        return $this->render('projet_arret/index.html.twig', [
            'projets_arret' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_projet_arret_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $projetArret = new ProjetArret();
        $form = $this->createForm(ProjetArretType::class, $projetArret);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($projetArret);
            $entityManager->flush();

            return $this->redirectToRoute('app_projet_arret_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('projet_arret/new.html.twig', [
            'projet_arret' => $projetArret,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_projet_arret_show', methods: ['GET'])]
    public function show(ProjetArret $projetArret): Response
    {
        return $this->render('projet_arret/show.html.twig', [
            'projet_arret' => $projetArret,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_projet_arret_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ProjetArret $projetArret, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProjetArretType::class, $projetArret);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_projet_arret_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('projet_arret/edit.html.twig', [
            'projet_arret' => $projetArret,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_projet_arret_delete', methods: ['POST'])]
    public function delete(Request $request, ProjetArret $projetArret, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$projetArret->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($projetArret);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_projet_arret_index', [], Response::HTTP_SEE_OTHER);
    }
}
