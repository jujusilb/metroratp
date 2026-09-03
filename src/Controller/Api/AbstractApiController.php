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

    /**
     * Le SQL brut renvoie les colonnes `tinyint` telles quelles (0/1/NULL, un entier PHP, pas un
     * booleen) : json_encode les serialise alors en nombre brut plutot qu'en `true`/`false`, ce
     * qu'un client JSON (Kotlin `Boolean?` compris) ne peut pas deserialiser correctement. Cast
     * explicite ici plutot que de laisser fuiter la representation de stockage MySQL.
     *
     * @param list<array<string, mixed>> $lignes
     * @param string[]                   $champsBooleens
     *
     * @return list<array<string, mixed>>
     */
    protected function castBooleens(array $lignes, array $champsBooleens): array
    {
        foreach ($lignes as &$ligne) {
            foreach ($champsBooleens as $champ) {
                if (null !== $ligne[$champ]) {
                    $ligne[$champ] = (bool) $ligne[$champ];
                }
            }
        }

        return $lignes;
    }
}
