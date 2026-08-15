<?php

namespace App\Command;

use App\Entity\PointDeVente;
use App\Entity\Station;
use App\Repository\PointDeVenteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les PointDeVente (dataset IDFM "points-de-vente", 2012 commerces agrees/guichets
 * Navigo) et les rattache a la Station la plus proche (a vol d'oiseau, dans un rayon de 300 m).
 *
 * Le dataset source ne donne aucun rattachement fiable a une Station (ZdAId toujours a 0 dans
 * cet export) : seule une adresse + des coordonnees GPS sont disponibles. Le rattachement par
 * proximite est donc une approximation assumee (voir PointDeVente), pas une donnee officielle -
 * distance calculee en PHP (conversion degres -> metres approximative, suffisante a l'echelle de
 * l'Ile-de-France, meme principe que assets/js/trajet-carte.js) plutot qu'en SQL geospatial (pas
 * d'index spatial sur ce schema).
 */
#[AsCommand(name: 'app:importer-points-de-vente', description: 'Importe les PointDeVente et les rattache a la Station la plus proche')]
class ImporterPointsDeVenteCommand extends Command
{
    private const POINTS_DE_VENTE_CSV = 'documentation/scripts/donnees-extraites/points-de-vente.csv';
    private const DISTANCE_MAX_METRES = 300;
    private const COS_LATITUDE_IDF = 0.6577; // cos(48.85°), reference Ile-de-France

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PointDeVenteRepository $pointDeVenteRepository,
    ) {
        parent::__construct();
    }

    /**
     * @return \Generator<int, array<string, string>>
     */
    private function lireCsv(string $chemin): \Generator
    {
        $fichier = fopen($chemin, 'r');
        $header = fgetcsv($fichier, separator: ';');
        $header[0] = preg_replace('/^\x{FEFF}+/u', '', $header[0]);
        while (false !== ($ligne = fgetcsv($fichier, separator: ';'))) {
            yield array_combine($header, $ligne);
        }
        fclose($fichier);
    }

    private function distanceCarreeMetres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dx = ($lon2 - $lon1) * 111320 * self::COS_LATITUDE_IDF;
        $dy = ($lat2 - $lat1) * 111320;

        return $dx ** 2 + $dy ** 2;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Chargement des Stations avec coordonnees...');
        $stations = [];
        foreach ($connexion->executeQuery('SELECT id, latitude, longitude FROM station WHERE latitude IS NOT NULL')->iterateAssociative() as $row) {
            $stations[] = ['id' => (int) $row['id'], 'lat' => (float) $row['latitude'], 'lon' => (float) $row['longitude']];
        }
        $io->info(\count($stations).' Stations avec coordonnees.');

        $io->section('Import des PointDeVente...');
        $seuilCarre = self::DISTANCE_MAX_METRES ** 2;
        $nbCrees = 0;
        $nbMaj = 0;
        $nbAvecStation = 0;

        foreach ($this->lireCsv(self::POINTS_DE_VENTE_CSV) as $ligne) {
            if ('' === $ligne['PdVLatitude'] || '' === $ligne['PdVLongitude']) {
                continue;
            }
            $lat = (float) $ligne['PdVLatitude'];
            $lon = (float) $ligne['PdVLongitude'];

            $meilleureDistance = null;
            $stationIdProche = null;
            foreach ($stations as $station) {
                $d = $this->distanceCarreeMetres($lat, $lon, $station['lat'], $station['lon']);
                if (null === $meilleureDistance || $d < $meilleureDistance) {
                    $meilleureDistance = $d;
                    $stationIdProche = $station['id'];
                }
            }
            if (null !== $meilleureDistance && $meilleureDistance > $seuilCarre) {
                $stationIdProche = null;
            }

            $pdv = $this->pointDeVenteRepository->trouverParCodeExterne($ligne['PdVId']) ?? new PointDeVente();
            $estNouveau = null === $pdv->getId();

            $pdv->setCodeExterne($ligne['PdVId']);
            $pdv->setLabel($ligne['PdVName']);
            $pdv->setType('' !== $ligne['pdvtypename'] ? $ligne['pdvtypename'] : null);
            $adresse = trim($ligne['PdVHousenumber'].' '.$ligne['PdVStreet']);
            $pdv->setAdresse('' !== $adresse ? $adresse : null);
            $pdv->setCodePostal('' !== $ligne['PdVPostCode'] ? $ligne['PdVPostCode'] : null);
            $pdv->setVille('' !== $ligne['PdVTown'] ? $ligne['PdVTown'] : null);
            $pdv->setHoraires('' !== $ligne['PdVOpeningHours'] ? $ligne['PdVOpeningHours'] : null);
            $pdv->setLatitude($lat);
            $pdv->setLongitude($lon);
            $pdv->setStation(null !== $stationIdProche ? $this->entityManager->getReference(Station::class, $stationIdProche) : null);

            if ($estNouveau) {
                $this->entityManager->persist($pdv);
                ++$nbCrees;
            } else {
                ++$nbMaj;
            }
            if (null !== $stationIdProche) {
                ++$nbAvecStation;
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d PointDeVente crees, %d mis a jour, %d rattaches a une Station a moins de %dm.',
            $nbCrees,
            $nbMaj,
            $nbAvecStation,
            self::DISTANCE_MAX_METRES,
        ));

        return Command::SUCCESS;
    }
}
