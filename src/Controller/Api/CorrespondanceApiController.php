<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/correspondances')]
class CorrespondanceApiController extends AbstractApiController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route(name: 'api_correspondance_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $rows = $this->entityManager->getConnection()->executeQuery(
            <<<'SQL'
                SELECT id, distance, in_zone AS inZone, desserte_a_id AS desserteAId,
                       desserte_b_id AS desserteBId, direction_a_id AS directionAId,
                       direction_b_id AS directionBId
                FROM correspondance
                ORDER BY id
                SQL,
        )->fetchAllAssociative();

        return $this->jsonListe($this->castBooleens($rows, ['inZone']));
    }
}
