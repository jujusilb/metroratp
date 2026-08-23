<?php

namespace App\Controller;

use App\Entity\Ville;
use App\Repository\VilleRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pas de new/edit/delete : Ville est entierement peuplee par app:importer-villes (referentiel
 * geo.api.gouv.fr), un formulaire manuel n'aurait pas de sens pour ses champs JSON (frontiere,
 * codesPostaux).
 */
#[Route('/ville')]
final class VilleController extends AbstractController
{
    #[Route(name: 'app_ville_index', methods: ['GET'])]
    public function index(Request $request, VilleRepository $villeRepository, PaginatorInterface $paginator): Response
    {
        $qb = $villeRepository->createQueryBuilder('v')->orderBy('v.label', 'ASC');

        return $this->render('ville/index.html.twig', [
            'villes' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/{id}', name: 'app_ville_show', methods: ['GET'])]
    public function show(Ville $ville, VilleRepository $villeRepository): Response
    {
        return $this->render('ville/show.html.twig', [
            'ville' => $ville,
            'lignesConcernees' => $villeRepository->trouverLignesConcernees($ville),
        ]);
    }
}
