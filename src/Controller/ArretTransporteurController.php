<?php

namespace App\Controller;

use App\Entity\ArretTransporteur;
use App\Form\ArretTransporteurType;
use App\Repository\ArretTransporteurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/arret-transporteur')]
final class ArretTransporteurController extends AbstractController
{
    #[Route(name: 'app_arret_transporteur_index', methods: ['GET'])]
    public function index(Request $request, ArretTransporteurRepository $arretTransporteurRepository, PaginatorInterface $paginator): Response
    {
        $qb = $arretTransporteurRepository->createQueryBuilder('a')
            ->leftJoin('a.station', 'station')->addSelect('station')
            ->orderBy('a.nom', 'ASC')
        ;

        return $this->render('arret_transporteur/index.html.twig', [
            'arretsTransporteur' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_arret_transporteur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $arretTransporteur = new ArretTransporteur();
        $form = $this->createForm(ArretTransporteurType::class, $arretTransporteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($arretTransporteur);
            $entityManager->flush();

            return $this->redirectToRoute('app_arret_transporteur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('arret_transporteur/new.html.twig', [
            'arret_transporteur' => $arretTransporteur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_arret_transporteur_show', methods: ['GET'])]
    public function show(ArretTransporteur $arretTransporteur): Response
    {
        return $this->render('arret_transporteur/show.html.twig', [
            'arret_transporteur' => $arretTransporteur,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_arret_transporteur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ArretTransporteur $arretTransporteur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ArretTransporteurType::class, $arretTransporteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_arret_transporteur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('arret_transporteur/edit.html.twig', [
            'arret_transporteur' => $arretTransporteur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_arret_transporteur_delete', methods: ['POST'])]
    public function delete(Request $request, ArretTransporteur $arretTransporteur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$arretTransporteur->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($arretTransporteur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_arret_transporteur_index', [], Response::HTTP_SEE_OTHER);
    }
}
