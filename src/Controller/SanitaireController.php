<?php

namespace App\Controller;

use App\Entity\Sanitaire;
use App\Form\SanitaireType;
use App\Repository\SanitaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sanitaire')]
final class SanitaireController extends AbstractController
{
    #[Route(name: 'app_sanitaire_index', methods: ['GET'])]
    public function index(Request $request, SanitaireRepository $sanitaireRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $sanitaireRepository->createQueryBuilder('s')
            ->leftJoin('s.station', 'station')->addSelect('station')
            ->orderBy('s.label', 'ASC')
        ;
        $sanitaireRepository->appliquerFiltreAlphabetEtRecherche($qb, 's.label', $lettre, $recherche);

        return $this->render('sanitaire/index.html.twig', [
            'sanitaires' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_sanitaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sanitaire = new Sanitaire();
        $form = $this->createForm(SanitaireType::class, $sanitaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($sanitaire);
            $entityManager->flush();

            return $this->redirectToRoute('app_sanitaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sanitaire/new.html.twig', [
            'sanitaire' => $sanitaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sanitaire_show', methods: ['GET'])]
    public function show(Sanitaire $sanitaire): Response
    {
        return $this->render('sanitaire/show.html.twig', [
            'sanitaire' => $sanitaire,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sanitaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sanitaire $sanitaire, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SanitaireType::class, $sanitaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_sanitaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sanitaire/edit.html.twig', [
            'sanitaire' => $sanitaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sanitaire_delete', methods: ['POST'])]
    public function delete(Request $request, Sanitaire $sanitaire, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$sanitaire->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($sanitaire);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_sanitaire_index', [], Response::HTTP_SEE_OTHER);
    }
}
