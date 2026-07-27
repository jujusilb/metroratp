<?php

namespace App\Controller;

use App\Entity\MaterielLigne;
use App\Form\MaterielLigneType;
use App\Repository\MaterielLigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/materiel/ligne')]
final class MaterielLigneController extends AbstractController
{
    #[Route(name: 'app_materiel_ligne_index', methods: ['GET'])]
    public function index(MaterielLigneRepository $materielLigneRepository): Response
    {
        return $this->render('materiel_ligne/index.html.twig', [
            'materiel_lignes' => $materielLigneRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_materiel_ligne_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $materielLigne = new MaterielLigne();
        $form = $this->createForm(MaterielLigneType::class, $materielLigne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($materielLigne);
            $entityManager->flush();

            return $this->redirectToRoute('app_materiel_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('materiel_ligne/new.html.twig', [
            'materiel_ligne' => $materielLigne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_materiel_ligne_show', methods: ['GET'])]
    public function show(MaterielLigne $materielLigne): Response
    {
        return $this->render('materiel_ligne/show.html.twig', [
            'materiel_ligne' => $materielLigne,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_materiel_ligne_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, MaterielLigne $materielLigne, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MaterielLigneType::class, $materielLigne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_materiel_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('materiel_ligne/edit.html.twig', [
            'materiel_ligne' => $materielLigne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_materiel_ligne_delete', methods: ['POST'])]
    public function delete(Request $request, MaterielLigne $materielLigne, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$materielLigne->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($materielLigne);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_materiel_ligne_index', [], Response::HTTP_SEE_OTHER);
    }
}
