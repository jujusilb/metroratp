<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/lignes')]
class LigneApiController extends AbstractApiController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Le trace geometrique (Ligne::trace, potentiellement volumineux) n'est pas inclus ici -
     * prevu comme endpoint dedie plus tard si besoin (/api/lignes/{id}/trace), pour ne pas alourdir
     * inutilement une liste consultee pour ses infos de base.
     */
    #[Route(name: 'api_ligne_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $rows = $this->entityManager->getConnection()->executeQuery(
            <<<'SQL'
                SELECT id, label, couleur, code_externe AS codeExterne,
                       type_transport_id AS typeTransportId, gestionnaire_id AS gestionnaireId,
                       date_automatisation_totale AS dateAutomatisationTotale
                FROM ligne
                ORDER BY id
                SQL,
        )->fetchAllAssociative();

        return $this->jsonListe($rows);
    }
}
