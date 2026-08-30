<?php

namespace App\Controller;

use App\Entity\TronconDesserte;
use App\Form\TronconDesserteType;
use App\Repository\TronconDesserteRepository;
use App\Repository\TronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/troncon-desserte')]
final class TronconDesserteController extends AbstractController
{
    #[Route(name: 'app_troncon_desserte_index', methods: ['GET'])]
    public function index(Request $request, TronconDesserteRepository $tronconDesserteRepository, PaginatorInterface $paginator): Response
    {
        $qb = $tronconDesserteRepository->createQueryBuilder('td')
            ->join('td.troncon', 't')->addSelect('t')
            ->join('td.desserte', 'd')->addSelect('d')
            ->join('d.station', 's')->addSelect('s')
            ->join('td.typeDesserte', 'ty')->addSelect('ty')
            ->orderBy('t.id', 'ASC')
        ;

        return $this->render('troncon_desserte/index.html.twig', [
            'troncon_dessertes' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    /**
     * Reachee uniquement via le bouton "Ajouter une desserte" de la fiche Troncon (?troncon={id}) :
     * voir TronconDesserteType, le Troncon fixe le contexte plutot que d'etre un champ du
     * formulaire.
     */
    #[Route('/new', name: 'app_troncon_desserte_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, TronconRepository $tronconRepository): Response
    {
        $troncon = $tronconRepository->find($request->query->getInt('troncon'));
        if (null === $troncon) {
            $this->addFlash('error', 'Choisissez un tronçon, puis cliquez sur « Ajouter une desserte » depuis sa fiche.');

            return $this->redirectToRoute('app_troncon_index');
        }

        $premiere = $troncon->getTronconDessertes()->first();
        $ligne = false !== $premiere ? $premiere->getDesserte()?->getLigne() : null;

        $tronconDesserte = new TronconDesserte();
        $tronconDesserte->setTroncon($troncon);
        $form = $this->createForm(TronconDesserteType::class, $tronconDesserte, ['ligne' => $ligne]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tronconDesserte);
            $entityManager->flush();

            return $this->redirectToRoute('app_troncon_show', ['id' => $troncon->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('troncon_desserte/new.html.twig', [
            'troncon_desserte' => $tronconDesserte,
            'troncon' => $troncon,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_troncon_desserte_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(TronconDesserte $tronconDesserte): Response
    {
        return $this->render('troncon_desserte/show.html.twig', [
            'troncon_desserte' => $tronconDesserte,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_troncon_desserte_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, TronconDesserte $tronconDesserte, EntityManagerInterface $entityManager): Response
    {
        $ligne = $tronconDesserte->getDesserte()?->getLigne();
        $form = $this->createForm(TronconDesserteType::class, $tronconDesserte, ['ligne' => $ligne]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_troncon_show', ['id' => $tronconDesserte->getTroncon()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('troncon_desserte/edit.html.twig', [
            'troncon_desserte' => $tronconDesserte,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_troncon_desserte_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, TronconDesserte $tronconDesserte, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tronconDesserte->getId(), $request->getPayload()->getString('_token'))) {
            $tronconId = $tronconDesserte->getTroncon()->getId();
            $entityManager->remove($tronconDesserte);
            $entityManager->flush();

            return $this->redirectToRoute('app_troncon_show', ['id' => $tronconId], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_troncon_desserte_index', [], Response::HTTP_SEE_OTHER);
    }
}
