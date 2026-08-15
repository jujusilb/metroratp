<?php

namespace App\Controller;

use App\Entity\Defibrillateur;
use App\Form\DefibrillateurType;
use App\Repository\DefibrillateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/defibrillateur')]
final class DefibrillateurController extends AbstractController
{
    #[Route(name: 'app_defibrillateur_index', methods: ['GET'])]
    public function index(Request $request, DefibrillateurRepository $defibrillateurRepository, PaginatorInterface $paginator): Response
    {
        $qb = $defibrillateurRepository->createQueryBuilder('d')->orderBy('d.localisation', 'ASC');

        return $this->render('defibrillateur/index.html.twig', [
            'defibrillateurs' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_defibrillateur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $defibrillateur = new Defibrillateur();
        $form = $this->createForm(DefibrillateurType::class, $defibrillateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($defibrillateur);
            $entityManager->flush();

            return $this->redirectToRoute('app_defibrillateur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('defibrillateur/new.html.twig', [
            'defibrillateur' => $defibrillateur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_defibrillateur_show', methods: ['GET'])]
    public function show(Defibrillateur $defibrillateur): Response
    {
        return $this->render('defibrillateur/show.html.twig', [
            'defibrillateur' => $defibrillateur,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_defibrillateur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Defibrillateur $defibrillateur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DefibrillateurType::class, $defibrillateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_defibrillateur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('defibrillateur/edit.html.twig', [
            'defibrillateur' => $defibrillateur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_defibrillateur_delete', methods: ['POST'])]
    public function delete(Request $request, Defibrillateur $defibrillateur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$defibrillateur->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($defibrillateur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_defibrillateur_index', [], Response::HTTP_SEE_OTHER);
    }
}
