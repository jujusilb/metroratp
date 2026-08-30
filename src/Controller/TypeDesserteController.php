<?php

namespace App\Controller;

use App\Entity\TypeDesserte;
use App\Form\TypeDesserteType;
use App\Repository\TypeDesserteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/type/desserte')]
final class TypeDesserteController extends AbstractController
{
    #[Route(name: 'app_type_desserte_index', methods: ['GET'])]
    public function index(Request $request, TypeDesserteRepository $typeDesserteRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $typeDesserteRepository->createQueryBuilder('t')->orderBy('t.label', 'ASC');
        $typeDesserteRepository->appliquerFiltreAlphabetEtRecherche($qb, 't.label', $lettre, $recherche);

        return $this->render('type_desserte/index.html.twig', [
            'type_dessertes' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_type_desserte_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $typeDesserte = new TypeDesserte();
        $form = $this->createForm(TypeDesserteType::class, $typeDesserte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($typeDesserte);
            $entityManager->flush();

            return $this->redirectToRoute('app_type_desserte_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type_desserte/new.html.twig', [
            'type_desserte' => $typeDesserte,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_type_desserte_show', methods: ['GET'])]
    public function show(TypeDesserte $typeDesserte): Response
    {
        return $this->render('type_desserte/show.html.twig', [
            'type_desserte' => $typeDesserte,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_desserte_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TypeDesserte $typeDesserte, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TypeDesserteType::class, $typeDesserte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_type_desserte_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('type_desserte/edit.html.twig', [
            'type_desserte' => $typeDesserte,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_type_desserte_delete', methods: ['POST'])]
    public function delete(Request $request, TypeDesserte $typeDesserte, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$typeDesserte->getId(), $request->getPayload()->getString('_token'))) {
            // Verrou : "Départ"/"Arrivée" sont references en dur par le calculateur de trajet et
            // plusieurs commandes d'import (voir TypeDesserteType) - les supprimer casserait tout
            // ça silencieusement, bien au-dela de l'erreur de contrainte de cle etrangere que la
            // base leverait de toute facon si des TronconDesserte y sont encore rattaches.
            if (\in_array($typeDesserte->getLabel(), TypeDesserteType::LABELS_VERROUILLES, true)) {
                $this->addFlash('error', 'Ce type de desserte est verrouillé (utilisé en dur par le calculateur de trajet) et ne peut pas être supprimé.');

                return $this->redirectToRoute('app_type_desserte_show', ['id' => $typeDesserte->getId()], Response::HTTP_SEE_OTHER);
            }

            $entityManager->remove($typeDesserte);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_type_desserte_index', [], Response::HTTP_SEE_OTHER);
    }
}
