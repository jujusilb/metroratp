<?php

namespace App\Controller;

use App\Repository\PlanRepository;
use App\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/carte')]
final class CarteController extends AbstractController
{
    #[Route(name: 'app_carte_index', methods: ['GET'])]
    public function index(StationRepository $stationRepository, PlanRepository $planRepository): Response
    {
        return $this->render('carte/index.html.twig', [
            'donneesJson' => json_encode($stationRepository->donneesPourCarteComplete(), JSON_THROW_ON_ERROR),
            'plans' => $planRepository->findBy([], ['secteur' => 'ASC']),
        ]);
    }
}
