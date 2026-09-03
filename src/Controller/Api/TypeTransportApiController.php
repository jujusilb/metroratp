<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API JSON en lecture seule, pour le portage Kotlin/Android (export en base locale embarquee) -
 * voir TODO.md. SQL brut plutot que l'ORM/serializer (meme raisonnement que
 * StationRepository::donneesPourCarteComplete()) : evite l'hydratation d'entites et les problemes
 * de reference circulaire, controle exact de la forme du JSON expose.
 */
#[Route('/api/types-transport')]
class TypeTransportApiController extends AbstractApiController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route(name: 'api_type_transport_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $rows = $this->entityManager->getConnection()->executeQuery(
            'SELECT id, label FROM type_transport ORDER BY id',
        )->fetchAllAssociative();

        return $this->jsonListe($rows);
    }
}
