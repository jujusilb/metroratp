<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Base commune des controleurs API JSON en lecture seule (voir TODO.md, portage Kotlin/Android).
 */
abstract class AbstractApiController extends AbstractController
{
    /**
     * @param list<array<string, mixed>> $lignes
     */
    protected function jsonListe(array $lignes): JsonResponse
    {
        return new JsonResponse($lignes, 200, [], false);
    }
}
