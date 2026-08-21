<?php

namespace App\Controller;

use App\Entity\Ligne;
use App\Form\LigneType;
use App\Repository\GestionnaireRepository;
use App\Repository\LigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ligne')]
final class LigneController extends AbstractController
{
    /** @var string[] */
    private const MODES_DISPONIBLES = ['metro', 'tram', 'rer', 'bus_ratp', 'bus_tiers', 'telepherique', 'funiculaire'];

    #[Route(name: 'app_ligne_index', methods: ['GET'])]
    public function index(Request $request, LigneRepository $ligneRepository, GestionnaireRepository $gestionnaireRepository, PaginatorInterface $paginator): Response
    {
        // Au premier chargement (aucune case n'a encore ete soumise), tout est coche par
        // defaut : comportement historique (liste complete), non restreint.
        $modesSelectionnes = $request->query->has('modes')
            ? array_intersect($request->query->all('modes'), self::MODES_DISPONIBLES)
            : self::MODES_DISPONIBLES;
        $recherche = $request->query->get('q');
        $gestionnairesSelectionnes = array_map('intval', $request->query->all('gestionnaires'));
        // "" (option "Tous") ne doit pas etre confondu avec une valeur absente : filtre_liste.html.twig
        // n'affiche le select "Tronçons construits" que si cette variable est definie (meme null).
        $avecTronconsBrut = $request->query->get('avecTroncons');
        $avecTroncons = '' === $avecTronconsBrut || null === $avecTronconsBrut ? null : '1' === $avecTronconsBrut;

        $lignes = $paginator->paginate(
            $ligneRepository->creerRequeteFiltree($modesSelectionnes, $recherche, $gestionnairesSelectionnes, $avecTroncons),
            $request->query->getInt('page', 1),
            50,
        );

        return $this->render('ligne/index.html.twig', [
            'lignes' => $lignes,
            'modesSelectionnes' => $modesSelectionnes,
            'recherche' => $recherche,
            'gestionnaires' => $gestionnaireRepository->findBy([], ['label' => 'ASC']),
            'gestionnairesSelectionnes' => $gestionnairesSelectionnes,
            'avecTroncons' => $avecTronconsBrut,
        ]);
    }

    #[Route('/new', name: 'app_ligne_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ligne = new Ligne();
        $form = $this->createForm(LigneType::class, $ligne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($ligne);
            $entityManager->flush();

            return $this->redirectToRoute('app_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ligne/new.html.twig', [
            'ligne' => $ligne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ligne_show', methods: ['GET'])]
    public function show(Ligne $ligne): Response
    {
        return $this->render('ligne/show.html.twig', [
            'ligne' => $ligne,
        ]);
    }

    /**
     * Pour la carte du reseau (carte-reseau.js) : trace geometrique reel d'une Ligne, recupere a
     * la demande (survol/clic d'une entree dans la bulle d'une station) plutot que d'embarquer les
     * traces de ~1445 Lignes dans le payload initial de la page.
     */
    #[Route('/{id}/trace', name: 'app_ligne_trace', methods: ['GET'])]
    public function trace(Ligne $ligne): JsonResponse
    {
        return $this->json([
            'label' => $ligne->getLabel(),
            'couleur' => $ligne->getCouleur(),
            'trace' => $ligne->getTrace(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_ligne_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Ligne $ligne, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LigneType::class, $ligne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ligne/edit.html.twig', [
            'ligne' => $ligne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ligne_delete', methods: ['POST'])]
    public function delete(Request $request, Ligne $ligne, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ligne->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($ligne);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_ligne_index', [], Response::HTTP_SEE_OTHER);
    }
}
