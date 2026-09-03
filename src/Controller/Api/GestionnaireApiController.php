<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/gestionnaires')]
class GestionnaireApiController extends AbstractApiController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route(name: 'api_gestionnaire_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $rows = $this->entityManager->getConnection()->executeQuery(
            'SELECT id, label FROM gestionnaire ORDER BY id',
        )->fetchAllAssociative();

        return $this->jsonListe($rows);
    }
}
