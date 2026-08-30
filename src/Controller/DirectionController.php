<?php

namespace App\Controller;

use App\Entity\Direction;
use App\Form\DirectionType;
use App\Repository\DirectionRepository;
use App\Repository\LigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/direction')]
final class DirectionController extends AbstractController
{
    #[Route(name: 'app_direction_index', methods: ['GET'])]
    public function index(Request $request, DirectionRepository $directionRepository, PaginatorInterface $paginator): Response
    {
        $qb = $directionRepository->createQueryBuilder('d')
            ->join('d.ligne', 'l')->addSelect('l')
            ->join('d.desserteTerminus', 'dt')->addSelect('dt')
            ->join('dt.station', 's')->addSelect('s')
            ->orderBy('l.label', 'ASC')
        ;

        return $this->render('direction/index.html.twig', [
            'directions' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    /**
     * Reachee uniquement via le bouton "Ajouter une direction" de la fiche Ligne (?ligne={id}) :
     * voir DirectionType, la Ligne fixe le contexte plutot que d'etre un champ du formulaire.
     */
    #[Route('/new', name: 'app_direction_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, LigneRepository $ligneRepository): Response
    {
        $ligne = $ligneRepository->find($request->query->getInt('ligne'));
        if (null === $ligne) {
            $this->addFlash('error', 'Choisissez une ligne, puis cliquez sur « Ajouter une direction » depuis sa fiche.');

            return $this->redirectToRoute('app_ligne_index');
        }

        $direction = new Direction();
        $direction->setLigne($ligne);
        $form = $this->createForm(DirectionType::class, $direction, ['ligne' => $ligne]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($direction);
            $entityManager->flush();

            return $this->redirectToRoute('app_ligne_show', ['id' => $ligne->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('direction/new.html.twig', [
            'direction' => $direction,
            'ligne' => $ligne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_direction_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Direction $direction): Response
    {
        return $this->render('direction/show.html.twig', [
            'direction' => $direction,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_direction_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Direction $direction, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DirectionType::class, $direction, ['ligne' => $direction->getLigne()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_ligne_show', ['id' => $direction->getLigne()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('direction/edit.html.twig', [
            'direction' => $direction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_direction_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Direction $direction, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$direction->getId(), $request->getPayload()->getString('_token'))) {
            $ligneId = $direction->getLigne()->getId();
            $entityManager->remove($direction);
            $entityManager->flush();

            return $this->redirectToRoute('app_ligne_show', ['id' => $ligneId], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_direction_index', [], Response::HTTP_SEE_OTHER);
    }
}
