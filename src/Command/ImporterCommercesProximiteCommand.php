<?php

namespace App\Command;

use App\Entity\PointDeVente;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Enrichit les PointDeVente de type "Commerce de proximite" avec la categorie fine du commerce
 * (cafe tabac, tabac presse, librairie...) et son jour de fermeture, depuis le dataset IDFM
 * "commerces-de-proximite-agrees-ratp" (911 lignes).
 *
 * Verifie AVANT d'importer que ce dataset chevauche tres largement points-de-vente.csv (deja
 * importe) : recoupement geographique (< 50m) confirme 889/911 (98%) deja presents en base -
 * confirme l'hypothese de TODO.md ("chevauchement probable"). Ces lignes ne creent donc PAS de
 * doublon : la commande met a jour la PointDeVente existante la plus proche plutot que d'en creer
 * une nouvelle. Seules les ~2% non retrouvees (commerces agrees RATP absents du referentiel
 * points-de-vente officiel) sont creees en plus, avec un codeExterne prefixe "COM-" (dataset
 * source distinct de PdVId, pas de collision possible).
 */
#[AsCommand(name: 'app:importer-commerces-proximite', description: 'Enrichit les PointDeVente "Commerce de proximite" avec la categorie fine et le jour de fermeture')]
class ImporterCommercesProximiteCommand extends Command
{
    private const COMMERCES_CSV = 'documentation/scripts/donnees-extraites/commerces-de-proximite-agrees-ratp.csv';
    private const DISTANCE_MATCH_METRES = 50;
    private const DISTANCE_MAX_STATION_METRES = 300;
    private const COS_LATITUDE_IDF = 0.6577; // cos(48.85°), reference Ile-de-France
    private const TYPE_COMMERCE_PROXIMITE = 'Commerce de proximité';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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

        $io->section('Chargement des PointDeVente "Commerce de proximite" existants...');
        $pointsDeVente = [];
        foreach ($connexion->executeQuery(
            'SELECT id, latitude, longitude FROM point_de_vente WHERE type = ?',
            [self::TYPE_COMMERCE_PROXIMITE]
        )->iterateAssociative() as $row) {
            $pointsDeVente[] = ['id' => (int) $row['id'], 'lat' => (float) $row['latitude'], 'lon' => (float) $row['longitude']];
        }
        $io->info(\count($pointsDeVente).' PointDeVente "Commerce de proximite" en base.');

        $io->section('Chargement des Stations avec coordonnees...');
        $stations = [];
        foreach ($connexion->executeQuery('SELECT id, latitude, longitude FROM station WHERE latitude IS NOT NULL')->iterateAssociative() as $row) {
            $stations[] = ['id' => (int) $row['id'], 'lat' => (float) $row['latitude'], 'lon' => (float) $row['longitude']];
        }
        $io->info(\count($stations).' Stations avec coordonnees.');

        $io->section('Lecture de commerces-de-proximite-agrees-ratp.csv...');
        $seuilMatchCarre = self::DISTANCE_MATCH_METRES ** 2;
        $seuilStationCarre = self::DISTANCE_MAX_STATION_METRES ** 2;
        $nbEnrichis = 0;
        $nbCrees = 0;

        foreach ($this->lireCsv(self::COMMERCES_CSV) as $ligne) {
            $coord = explode(',', $ligne['geocodage_ban']);
            if (2 !== \count($coord)) {
                continue;
            }
            $lat = (float) trim($coord[0]);
            $lon = (float) trim($coord[1]);

            $meilleureDistance = null;
            $pdvIdProche = null;
            foreach ($pointsDeVente as $pdv) {
                $d = $this->distanceCarreeMetres($lat, $lon, $pdv['lat'], $pdv['lon']);
                if (null === $meilleureDistance || $d < $meilleureDistance) {
                    $meilleureDistance = $d;
                    $pdvIdProche = $pdv['id'];
                }
            }

            $categorie = '' !== $ligne['commerce'] ? $ligne['commerce'] : null;
            $jourFermeture = '' !== $ligne['DEA_JOUR FERMETURE'] ? $ligne['DEA_JOUR FERMETURE'] : null;

            if (null !== $meilleureDistance && $meilleureDistance <= $seuilMatchCarre) {
                $connexion->executeStatement(
                    'UPDATE point_de_vente SET categorie_commerce = ?, jour_fermeture = ? WHERE id = ?',
                    [$categorie, $jourFermeture, $pdvIdProche]
                );
                ++$nbEnrichis;
                continue;
            }

            // Aucun PointDeVente existant a proximite : commerce agree RATP absent du referentiel
            // points-de-vente officiel, cree en plus (voir docblock de la classe).
            $meilleureDistanceStation = null;
            $stationIdProche = null;
            foreach ($stations as $station) {
                $d = $this->distanceCarreeMetres($lat, $lon, $station['lat'], $station['lon']);
                if (null === $meilleureDistanceStation || $d < $meilleureDistanceStation) {
                    $meilleureDistanceStation = $d;
                    $stationIdProche = $station['id'];
                }
            }
            if (null !== $meilleureDistanceStation && $meilleureDistanceStation > $seuilStationCarre) {
                $stationIdProche = null;
            }

            $pdv = new PointDeVente();
            $pdv->setCodeExterne('COM-'.$ligne['identifiant commerce']);
            $pdv->setLabel($ligne['DEA_NOM_COMMERCE']);
            $pdv->setType(self::TYPE_COMMERCE_PROXIMITE);
            $adresse = trim($ligne['DEA_RUE   _LIVRAISON']);
            $pdv->setAdresse('' !== $adresse ? $adresse : null);
            $pdv->setCodePostal('' !== $ligne['DEA_CP_  LIVRAISON'] ? $ligne['DEA_CP_  LIVRAISON'] : null);
            $pdv->setVille('' !== $ligne['DEA_COMMUNE_LIVRAISON'] ? $ligne['DEA_COMMUNE_LIVRAISON'] : null);
            $pdv->setCategorieCommerce($categorie);
            $pdv->setJourFermeture($jourFermeture);
            $pdv->setLatitude($lat);
            $pdv->setLongitude($lon);
            $pdv->setStation(null !== $stationIdProche ? $this->entityManager->getReference(Station::class, $stationIdProche) : null);

            $this->entityManager->persist($pdv);
            ++$nbCrees;
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d PointDeVente enrichis (categorie + jour de fermeture), %d crees en plus (commerces agrees absents du referentiel officiel).',
            $nbEnrichis,
            $nbCrees,
        ));

        return Command::SUCCESS;
    }
}
