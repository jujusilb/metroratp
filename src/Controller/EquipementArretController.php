<?php

namespace App\Controller;

use App\Entity\EquipementArret;
use App\Form\EquipementArretType;
use App\Repository\EquipementArretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/equipement-arret')]
final class EquipementArretController extends AbstractController
{
    #[Route(name: 'app_equipement_arret_index', methods: ['GET'])]
    public function index(Request $request, EquipementArretRepository $equipementArretRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $equipementArretRepository->createQueryBuilder('e')
            ->leftJoin('e.station', 'station')->addSelect('station')
            ->orderBy('e.nom', 'ASC')
        ;
        $equipementArretRepository->appliquerFiltreAlphabetEtRecherche($qb, 'e.nom', $lettre, $recherche);

        return $this->render('equipement_arret/index.html.twig', [
            'equipementArrets' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_equipement_arret_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $equipementArret = new EquipementArret();
        $form = $this->createForm(EquipementArretType::class, $equipementArret);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($equipementArret);
            $entityManager->flush();

            return $this->redirectToRoute('app_equipement_arret_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('equipement_arret/new.html.twig', [
            'equipement_arret' => $equipementArret,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_equipement_arret_show', methods: ['GET'])]
    public function show(EquipementArret $equipementArret): Response
    {
        return $this->render('equipement_arret/show.html.twig', [
            'equipement_arret' => $equipementArret,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_equipement_arret_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EquipementArret $equipementArret, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EquipementArretType::class, $equipementArret);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_equipement_arret_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('equipement_arret/edit.html.twig', [
            'equipement_arret' => $equipementArret,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_equipement_arret_delete', methods: ['POST'])]
    public function delete(Request $request, EquipementArret $equipementArret, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$equipementArret->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($equipementArret);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_equipement_arret_index', [], Response::HTTP_SEE_OTHER);
    }
}
