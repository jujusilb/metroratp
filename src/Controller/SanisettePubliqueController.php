<?php

namespace App\Controller;

use App\Entity\SanisettePublique;
use App\Form\SanisettePubliqueType;
use App\Repository\SanisettePubliqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sanisette-publique')]
final class SanisettePubliqueController extends AbstractController
{
    #[Route(name: 'app_sanisette_publique_index', methods: ['GET'])]
    public function index(Request $request, SanisettePubliqueRepository $sanisettePubliqueRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $sanisettePubliqueRepository->createQueryBuilder('s')
            ->leftJoin('s.station', 'station')->addSelect('station')
            ->orderBy('s.adresse', 'ASC')
        ;
        $sanisettePubliqueRepository->appliquerFiltreAlphabetEtRecherche($qb, 's.adresse', $lettre, $recherche);

        return $this->render('sanisette_publique/index.html.twig', [
            'sanisettes' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_sanisette_publique_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sanisette = new SanisettePublique();
        $form = $this->createForm(SanisettePubliqueType::class, $sanisette);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($sanisette);
            $entityManager->flush();

            return $this->redirectToRoute('app_sanisette_publique_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sanisette_publique/new.html.twig', [
            'sanisette' => $sanisette,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sanisette_publique_show', methods: ['GET'])]
    public function show(SanisettePublique $sanisette): Response
    {
        return $this->render('sanisette_publique/show.html.twig', [
            'sanisette' => $sanisette,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sanisette_publique_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SanisettePublique $sanisette, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SanisettePubliqueType::class, $sanisette);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_sanisette_publique_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sanisette_publique/edit.html.twig', [
            'sanisette' => $sanisette,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sanisette_publique_delete', methods: ['POST'])]
    public function delete(Request $request, SanisettePublique $sanisette, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$sanisette->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($sanisette);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_sanisette_publique_index', [], Response::HTTP_SEE_OTHER);
    }
}
