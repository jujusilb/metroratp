<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stations')]
class StationApiController extends AbstractApiController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * schema_x/schema_y (coordonnees du plan schematique RATP) volontairement exclues - propres a
     * l'affichage du plan interactif de ce site, sans usage pour un client externe qui a deja
     * latitude/longitude (coordonnees GPS reelles).
     */
    #[Route(name: 'api_station_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $rows = $this->entityManager->getConnection()->executeQuery(
            <<<'SQL'
                SELECT id, label, code_externe AS codeExterne, ville, latitude, longitude,
                       zone_tarifaire AS zoneTarifaire, accessibilite_pmr AS accessibilitePmr,
                       accessibilite_pmr_commentaire AS accessibilitePmrCommentaire,
                       pole_echange_id AS poleEchangeId, ville_ref_id AS villeRefId
                FROM station
                ORDER BY id
                SQL,
        )->fetchAllAssociative();

        return $this->jsonListe($rows);
    }
}
