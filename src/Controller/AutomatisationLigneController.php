<?php

namespace App\Controller;

use App\Entity\AutomatisationLigne;
use App\Form\AutomatisationLigneType;
use App\Repository\AutomatisationLigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/automatisation/ligne')]
final class AutomatisationLigneController extends AbstractController
{
    #[Route(name: 'app_automatisation_ligne_index', methods: ['GET'])]
    public function index(Request $request, AutomatisationLigneRepository $automatisationLigneRepository, PaginatorInterface $paginator): Response
    {
        $qb = $automatisationLigneRepository->createQueryBuilder('al')
            ->leftJoin('al.automatisation', 'automatisation')->addSelect('automatisation')
            ->leftJoin('al.ligne', 'ligne')->addSelect('ligne')
        ;

        return $this->render('automatisation_ligne/index.html.twig', [
            'automatisation_lignes' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_automatisation_ligne_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $automatisationLigne = new AutomatisationLigne();
        $form = $this->createForm(AutomatisationLigneType::class, $automatisationLigne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($automatisationLigne);
            $entityManager->flush();

            return $this->redirectToRoute('app_automatisation_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('automatisation_ligne/new.html.twig', [
            'automatisation_ligne' => $automatisationLigne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_automatisation_ligne_show', methods: ['GET'])]
    public function show(AutomatisationLigne $automatisationLigne): Response
    {
        return $this->render('automatisation_ligne/show.html.twig', [
            'automatisation_ligne' => $automatisationLigne,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_automatisation_ligne_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, AutomatisationLigne $automatisationLigne, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AutomatisationLigneType::class, $automatisationLigne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_automatisation_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('automatisation_ligne/edit.html.twig', [
            'automatisation_ligne' => $automatisationLigne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_automatisation_ligne_delete', methods: ['POST'])]
    public function delete(Request $request, AutomatisationLigne $automatisationLigne, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$automatisationLigne->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($automatisationLigne);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_automatisation_ligne_index', [], Response::HTTP_SEE_OTHER);
    }
}
