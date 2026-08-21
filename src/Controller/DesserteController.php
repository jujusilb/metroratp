<?php

namespace App\Controller;

use App\Entity\Desserte;
use App\Form\DesserteType;
use App\Repository\DesserteRepository;
use App\Repository\GestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/desserte')]
final class DesserteController extends AbstractController
{
    /** @var string[] */
    private const MODES_DISPONIBLES = ['metro', 'tram', 'rer', 'bus_ratp', 'bus_tiers', 'telepherique', 'funiculaire', 'train'];

    #[Route(name: 'app_desserte_index', methods: ['GET'])]
    public function index(Request $request, DesserteRepository $desserteRepository, GestionnaireRepository $gestionnaireRepository, PaginatorInterface $paginator): Response
    {
        $modesSelectionnes = $request->query->has('modes')
            ? array_intersect($request->query->all('modes'), self::MODES_DISPONIBLES)
            : self::MODES_DISPONIBLES;
        $recherche = $request->query->get('q');
        $gestionnairesSelectionnes = array_map('intval', $request->query->all('gestionnaires'));

        // Pagination sur une requete legere (pas de fetch-join sur periodesOuverture, qui
        // multiplierait les lignes SQL et fausserait le compte total) : voir
        // DesserteRepository::creerRequeteFiltree(). Les entites completes de la page courante
        // sont rechargees ensuite en une seule requete supplementaire (trouverAvecDetailsParIds).
        $pagination = $paginator->paginate(
            $desserteRepository->creerRequeteFiltree($modesSelectionnes, $recherche, $gestionnairesSelectionnes),
            $request->query->getInt('page', 1),
            50,
        );

        $ids = array_map(static fn (Desserte $d): int => $d->getId(), $pagination->getItems());
        $pagination->setItems($desserteRepository->trouverAvecDetailsParIds($ids));

        return $this->render('desserte/index.html.twig', [
            'dessertes' => $pagination,
            'modesSelectionnes' => $modesSelectionnes,
            'recherche' => $recherche,
            'gestionnaires' => $gestionnaireRepository->findBy([], ['label' => 'ASC']),
            'gestionnairesSelectionnes' => $gestionnairesSelectionnes,
        ]);
    }

    #[Route('/new', name: 'app_desserte_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $desserte = new Desserte();
        $form = $this->createForm(DesserteType::class, $desserte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($desserte);
            $entityManager->flush();

            return $this->redirectToRoute('app_desserte_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('desserte/new.html.twig', [
            'desserte' => $desserte,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_desserte_show', methods: ['GET'])]
    public function show(Desserte $desserte): Response
    {
        return $this->render('desserte/show.html.twig', [
            'desserte' => $desserte,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_desserte_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Desserte $desserte, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DesserteType::class, $desserte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_desserte_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('desserte/edit.html.twig', [
            'desserte' => $desserte,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_desserte_delete', methods: ['POST'])]
    public function delete(Request $request, Desserte $desserte, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$desserte->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($desserte);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_desserte_index', [], Response::HTTP_SEE_OTHER);
    }
}
