<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dessertes')]
class DesserteApiController extends AbstractApiController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * La plus grosse table exposee ici (~31800 lignes) : verifie que ca reste dans la limite
     * memoire par defaut avant de la considerer comme suffisamment sure pour ne pas paginer (donnee
     * lue par un import ponctuel cote client, pas par un navigateur - un envoi complet a chaque
     * fois est le comportement voulu, pas un defaut a corriger).
     */
    #[Route(name: 'api_desserte_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $rows = $this->entityManager->getConnection()->executeQuery(
            <<<'SQL'
                SELECT id, station_id AS stationId, ligne_id AS ligneId,
                       style_station_id AS styleStationId, style_ecriture_id AS styleEcritureId,
                       equipement_arret_id AS equipementArretId, est_accessible AS estAccessible,
                       signalisation_sonore AS signalisationSonore,
                       signalisation_visuelle AS signalisationVisuelle, climatisation,
                       date_porte_paliere AS datePortePaliere
                FROM desserte
                ORDER BY id
                SQL,
        )->fetchAllAssociative();

        return $this->jsonListe($this->castBooleens($rows, ['estAccessible', 'signalisationSonore', 'signalisationVisuelle']));
    }
}
