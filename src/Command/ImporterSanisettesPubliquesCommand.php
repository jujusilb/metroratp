<?php

namespace App\Command;

use App\Entity\SanisettePublique;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les SanisettePublique (dataset Paris Open Data "sanisettesparis2011", 609 toilettes
 * publiques de voirie, distinctes des Sanitaire RATP en station). Aucune cle stable dans le CSV
 * source : purge + reimport complet a chaque execution. Rattachement a Station par proximite
 * geographique (le dataset ne fournit aucun identifiant de reseau) : 606/609 (99%) rattachees a
 * moins de 300m - Paris intra-muros est dense en arrets de bus, donc la plupart des sanisettes de
 * voirie se trouvent malgre tout pres d'un arret du reseau (pas seulement pres des stations
 * metro/RER).
 */
#[AsCommand(name: 'app:importer-sanisettes-publiques', description: 'Importe les SanisettePublique (toilettes publiques Ville de Paris) et les rattache a la Station la plus proche')]
class ImporterSanisettesPubliquesCommand extends Command
{
    private const SANISETTES_CSV = 'documentation/scripts/donnees-extraites/sanisettesparis2011.csv';
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

        $io->section('Purge des SanisettePublique existantes...');
        $connexion->executeStatement('DELETE FROM sanisette_publique');

        $io->section('Lecture de sanisettesparis2011.csv et creation des SanisettePublique...');
        $seuilCarre = self::DISTANCE_MAX_METRES ** 2;
        $nbCrees = 0;
        $nbAvecStation = 0;

        foreach ($this->lireCsv(self::SANISETTES_CSV) as $ligne) {
            $coord = explode(',', $ligne['geo_point_2d']);
            if (2 !== \count($coord)) {
                continue;
            }
            $lat = (float) trim($coord[0]);
            $lon = (float) trim($coord[1]);

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

            $sanisette = new SanisettePublique();
            $sanisette->setArrondissement('' !== $ligne['ARRONDISSEMENT'] ? $ligne['ARRONDISSEMENT'] : null);
            $sanisette->setType($ligne['TYPE']);
            $sanisette->setStatut($ligne['STATUT']);
            $sanisette->setAdresse($ligne['ADRESSE']);
            $sanisette->setHoraire('' !== $ligne['HORAIRE'] ? $ligne['HORAIRE'] : null);
            $sanisette->setAccesPmr($this->ouiNon($ligne['ACCES_PMR']));
            $sanisette->setRelaisBebe($this->ouiNon($ligne['RELAIS_BEBE']));
            $sanisette->setUrlFicheEquipement('' !== $ligne['URL_FICHE_EQUIPEMENT'] ? $ligne['URL_FICHE_EQUIPEMENT'] : null);
            $sanisette->setLatitude($lat);
            $sanisette->setLongitude($lon);
            $sanisette->setGestionnaire('' !== $ligne['gestionnaire'] ? trim($ligne['gestionnaire']) : null);
            $sanisette->setStation(null !== $stationIdProche ? $this->entityManager->getReference(Station::class, $stationIdProche) : null);

            $this->entityManager->persist($sanisette);
            ++$nbCrees;
            if (null !== $stationIdProche) {
                ++$nbAvecStation;
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d SanisettePublique creees, %d rattachees a une Station a moins de %dm.',
            $nbCrees,
            $nbAvecStation,
            self::DISTANCE_MAX_METRES,
        ));

        return Command::SUCCESS;
    }
}
