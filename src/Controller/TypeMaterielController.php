<?php

namespace App\Controller;

use App\Entity\TypeMateriel;
use App\Form\TypeMaterielType;
use App\Repository\TypeMaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/type/materiel')]
final class TypeMaterielController extends AbstractController
{
    #[Route(name: 'app_type_materiel_index', methods: ['GET'])]
    public function index(TypeMaterielRepository $typeMaterielRepository): Response
    {
        return $this->render('type_materiel/index.html.twig', [
            'type_materiels' => $typeMaterielRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_type_materiel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $typeMateriel = new TypeMateriel();
        $form = $this->createForm(TypeMaterielType::class, $typeMateriel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($typeMateriel);
            $entityManager->flush();

            return $this->redirectToRoute('app_type_materiel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type_materiel/new.html.twig', [
            'type_materiel' => $typeMateriel,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_type_materiel_show', methods: ['GET'])]
    public function show(TypeMateriel $typeMateriel): Response
    {
        return $this->render('type_materiel/show.html.twig', [
            'type_materiel' => $typeMateriel,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_materiel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TypeMateriel $typeMateriel, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TypeMaterielType::class, $typeMateriel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_type_materiel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type_materiel/edit.html.twig', [
            'type_materiel' => $typeMateriel,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_type_materiel_delete', methods: ['POST'])]
    public function delete(Request $request, TypeMateriel $typeMateriel, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$typeMateriel->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($typeMateriel);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_type_materiel_index', [], Response::HTTP_SEE_OTHER);
    }
}
