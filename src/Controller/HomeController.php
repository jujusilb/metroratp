<?php

namespace App\Controller;

use App\Repository\LigneRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(LigneRepository $ligneRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'lignes' => $ligneRepository->findBy([], ['id' => 'ASC']),
        ]);
    }
}
