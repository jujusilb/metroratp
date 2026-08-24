<?php

namespace App\Controller;

use App\Entity\StyleAcces;
use App\Form\StyleAccesType;
use App\Repository\StyleAccesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/style/acces')]
final class StyleAccesController extends AbstractController
{
    #[Route(name: 'app_style_acces_index', methods: ['GET'])]
    public function index(Request $request, StyleAccesRepository $styleAccesRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $styleAccesRepository->createQueryBuilder('s')->orderBy('s.label', 'ASC');
        $styleAccesRepository->appliquerFiltreAlphabetEtRecherche($qb, 's.label', $lettre, $recherche);

        return $this->render('style_acces/index.html.twig', [
            'style_acces_list' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_style_acces_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $styleAcces = new StyleAcces();
        $form = $this->createForm(StyleAccesType::class, $styleAcces);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($styleAcces);
            $entityManager->flush();

            return $this->redirectToRoute('app_style_acces_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('style_acces/new.html.twig', [
            'style_acces' => $styleAcces,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_style_acces_show', methods: ['GET'])]
    public function show(StyleAcces $styleAcces): Response
    {
        return $this->render('style_acces/show.html.twig', [
            'style_acces' => $styleAcces,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_style_acces_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, StyleAcces $styleAcces, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StyleAccesType::class, $styleAcces);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_style_acces_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('style_acces/edit.html.twig', [
            'style_acces' => $styleAcces,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_style_acces_delete', methods: ['POST'])]
    public function delete(Request $request, StyleAcces $styleAcces, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$styleAcces->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($styleAcces);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_style_acces_index', [], Response::HTTP_SEE_OTHER);
    }
}
