<?php

namespace App\Controller;

use App\Entity\Acces;
use App\Form\AccesType;
use App\Repository\AccesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/acces')]
final class AccesController extends AbstractController
{
    #[Route(name: 'app_acces_index', methods: ['GET'])]
    public function index(Request $request, AccesRepository $accesRepository, PaginatorInterface $paginator): Response
    {
        $qb = $accesRepository->createQueryBuilder('a')
            ->leftJoin('a.styleAcces', 'styleAcces')->addSelect('styleAcces')
            ->orderBy('a.label', 'ASC');

        return $this->render('acces/index.html.twig', [
            'acces' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_acces_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $acce = new Acces();
        $form = $this->createForm(AccesType::class, $acce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($acce);
            $entityManager->flush();

            return $this->redirectToRoute('app_acces_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('acces/new.html.twig', [
            'acce' => $acce,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_acces_show', methods: ['GET'])]
    public function show(Acces $acce): Response
    {
        return $this->render('acces/show.html.twig', [
            'acce' => $acce,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_acces_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Acces $acce, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AccesType::class, $acce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_acces_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('acces/edit.html.twig', [
            'acce' => $acce,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_acces_delete', methods: ['POST'])]
    public function delete(Request $request, Acces $acce, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$acce->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($acce);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_acces_index', [], Response::HTTP_SEE_OTHER);
    }
}
