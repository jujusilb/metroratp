<?php

namespace App\Command;

use App\Entity\Defibrillateur;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les Defibrillateur (dataset IDFM "defibrillateurs-du-reseau-ratp", 451 emplacements).
 * Aucune cle stable dans le CSV source : purge + reimport complet a chaque execution (comme
 * app:importer-sanitaires). Rattachement a Station par proximite geographique (le dataset ne
 * fournit pas d'identifiant de Station officiel, seulement une description de lieu et des
 * coordonnees).
 */
#[AsCommand(name: 'app:importer-defibrillateurs', description: 'Importe les Defibrillateur et les rattache a la Station la plus proche')]
class ImporterDefibrillateursCommand extends Command
{
    private const DEFIBRILLATEURS_CSV = 'documentation/scripts/donnees-extraites/defibrillateurs-du-reseau-ratp.csv';
    private const DISTANCE_MAX_METRES = 300;
    private const COS_LATITUDE_IDF = 0.6577; // cos(48.85°), reference Ile-de-France

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

    private function ouiNon(string $valeur): ?bool
    {
        return '' !== $valeur ? 'oui' === strtolower(trim($valeur)) : null;
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

        $io->section('Purge des Defibrillateur existants...');
        $connexion->executeStatement('DELETE FROM defibrillateur');

        $io->section('Lecture de defibrillateurs-du-reseau-ratp.csv et creation des Defibrillateur...');
        $seuilCarre = self::DISTANCE_MAX_METRES ** 2;
        $nbCrees = 0;
        $nbAvecStation = 0;

        foreach ($this->lireCsv(self::DEFIBRILLATEURS_CSV) as $ligne) {
            if ('' === $ligne['lat_coor1'] || '' === $ligne['long_coor1']) {
                continue;
            }
            $lat = (float) $ligne['lat_coor1'];
            $lon = (float) $ligne['long_coor1'];

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

            $defibrillateur = new Defibrillateur();
            $defibrillateur->setLocalisation($ligne['Localisation']);
            $defibrillateur->setCodePostal('' !== $ligne['Code postal'] ? $ligne['Code postal'] : null);
            $defibrillateur->setVille('' !== $ligne['Ville'] ? $ligne['Ville'] : null);
            $defibrillateur->setAcces('' !== $ligne['Accès'] ? $ligne['Accès'] : null);
            $defibrillateur->setAccesLibre($this->ouiNon($ligne['Accès Libre']));
            $defibrillateur->setComplementLocalisation('' !== $ligne['Complément de localisation'] ? $ligne['Complément de localisation'] : null);
            $defibrillateur->setDisponibiliteSemaine('' !== $ligne['Disponibilité Semaine'] ? $ligne['Disponibilité Semaine'] : null);
            $defibrillateur->setDisponibiliteHoraires('' !== $ligne['Disponibilité Horaires'] ? $ligne['Disponibilité Horaires'] : null);
            $defibrillateur->setLatitude($lat);
            $defibrillateur->setLongitude($lon);
            $defibrillateur->setStation(null !== $stationIdProche ? $this->entityManager->getReference(Station::class, $stationIdProche) : null);

            $this->entityManager->persist($defibrillateur);
            ++$nbCrees;
            if (null !== $stationIdProche) {
                ++$nbAvecStation;
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d Defibrillateur crees, %d rattaches a une Station a moins de %dm.',
            $nbCrees,
            $nbAvecStation,
            self::DISTANCE_MAX_METRES,
        ));

        return Command::SUCCESS;
    }
}
