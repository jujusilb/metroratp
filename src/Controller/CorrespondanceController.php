<?php

namespace App\Controller;

use App\Entity\Correspondance;
use App\Form\CorrespondanceType;
use App\Repository\CorrespondanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/correspondance')]
final class CorrespondanceController extends AbstractController
{
    #[Route(name: 'app_correspondance_index', methods: ['GET'])]
    public function index(Request $request, CorrespondanceRepository $correspondanceRepository, PaginatorInterface $paginator): Response
    {
        return $this->render('correspondance/index.html.twig', [
            'correspondances' => $paginator->paginate(
                $correspondanceRepository->creerRequeteAvecDetails(),
                $request->query->getInt('page', 1),
                50,
            ),
        ]);
    }

    #[Route('/new', name: 'app_correspondance_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CorrespondanceRepository $correspondanceRepository): Response
    {
        $correspondance = new Correspondance();
        $form = $this->createForm(CorrespondanceType::class, $correspondance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->correspondanceExisteDeja($correspondance, $correspondanceRepository)) {
                $form->get('directionB')->addError(new FormError(
                    'Une correspondance existe déjà pour cette combinaison de dessertes et de directions.'
                ));
            } else {
                $entityManager->persist($correspondance);
                $entityManager->flush();

                return $this->redirectToRoute('app_correspondance_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('correspondance/new.html.twig', [
            'correspondance' => $correspondance,
            'form' => $form,
        ]);
    }

    /**
     * La contrainte UNIQUE en base ne traite pas deux NULL comme identiques (donc ne bloque
     * pas un doublon de correspondance "generale", sans direction precisee) : cette verification
     * applicative comble ce trou avant l'ecriture.
     */
    private function correspondanceExisteDeja(Correspondance $correspondance, CorrespondanceRepository $correspondanceRepository): bool
    {
        if (null === $correspondance->getDesserteA() || null === $correspondance->getDesserteB()) {
            return false;
        }

        return $correspondanceRepository->existeDejaPourCombinaison(
            $correspondance->getDesserteA()->getId(),
            $correspondance->getDesserteB()->getId(),
            $correspondance->getDirectionA()?->getId(),
            $correspondance->getDirectionB()?->getId(),
            $correspondance->getId(),
        );
    }

    #[Route('/{id}', name: 'app_correspondance_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Correspondance $correspondance): Response
    {
        return $this->render('correspondance/show.html.twig', [
            'correspondance' => $correspondance,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_correspondance_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Correspondance $correspondance, EntityManagerInterface $entityManager, CorrespondanceRepository $correspondanceRepository): Response
    {
        $form = $this->createForm(CorrespondanceType::class, $correspondance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->correspondanceExisteDeja($correspondance, $correspondanceRepository)) {
                $form->get('directionB')->addError(new FormError(
                    'Une correspondance existe déjà pour cette combinaison de dessertes et de directions.'
                ));
            } else {
                $entityManager->flush();

                return $this->redirectToRoute('app_correspondance_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('correspondance/edit.html.twig', [
            'correspondance' => $correspondance,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_correspondance_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Correspondance $correspondance, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$correspondance->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($correspondance);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_correspondance_index', [], Response::HTTP_SEE_OTHER);
    }
}
