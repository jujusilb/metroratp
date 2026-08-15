<?php

namespace App\Command;

use App\Entity\Sanitaire;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les Sanitaire (dataset IDFM "sanitaires-reseau-ratp", 60 toilettes publiques en
 * station). Aucune cle stable dans le CSV source : purge + reimport complet a chaque execution
 * (comme app:importer-projets-arrets). Rattachement a Station par proximite geographique (le
 * dataset ne fournit pas d'identifiant de Station officiel, seulement des coordonnees).
 */
#[AsCommand(name: 'app:importer-sanitaires', description: 'Importe les Sanitaire (toilettes publiques en station) et les rattache a la Station la plus proche')]
class ImporterSanitairesCommand extends Command
{
    private const SANITAIRES_CSV = 'documentation/scripts/donnees-extraites/sanitaires-reseau-ratp.csv';
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

        $io->section('Purge des Sanitaire existants...');
        $connexion->executeStatement('DELETE FROM sanitaire');

        $io->section('Lecture de sanitaires-reseau-ratp.csv et creation des Sanitaire...');
        $seuilCarre = self::DISTANCE_MAX_METRES ** 2;
        $nbCrees = 0;
        $nbAvecStation = 0;

        foreach ($this->lireCsv(self::SANITAIRES_CSV) as $ligne) {
            $coord = explode(',', $ligne['coord_geo']);
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

            $sanitaire = new Sanitaire();
            $sanitaire->setLigneLabel('' !== $ligne['Ligne'] ? $ligne['Ligne'] : null);
            $sanitaire->setLabel($ligne['Station']);
            $sanitaire->setAccessiblePublic($this->ouiNon($ligne['Accessible au public']));
            $sanitaire->setTarif('' !== $ligne['Tarif [Gratuit|Payant]'] ? $ligne['Tarif [Gratuit|Payant]'] : null);
            $sanitaire->setAccesPassNavigoTicketT($this->ouiNon($ligne['Acces Passe Navigo ou Ticket T+']));
            $sanitaire->setAccesBoutonPoussoir($this->ouiNon($ligne['Acces Bouton poussoir']));
            $sanitaire->setEnZoneControlee($this->ouiNon($ligne['En zone controlee']));
            $sanitaire->setHorsZoneControleeStation($this->ouiNon($ligne['Hors zone controlee station']));
            $sanitaire->setHorsZoneControleeVoiePublique($this->ouiNon($ligne['Hors zone controlee voie publique']));
            $sanitaire->setAccessibilitePmr($this->ouiNon($ligne['Accessibilite PMR']));
            $sanitaire->setLocalisation('' !== $ligne['Localisation'] ? $ligne['Localisation'] : null);
            $sanitaire->setLatitude($lat);
            $sanitaire->setLongitude($lon);
            $sanitaire->setGestionnaire('' !== $ligne['gestionnaire'] ? trim($ligne['gestionnaire']) : null);
            $sanitaire->setStation(null !== $stationIdProche ? $this->entityManager->getReference(Station::class, $stationIdProche) : null);

            $this->entityManager->persist($sanitaire);
            ++$nbCrees;
            if (null !== $stationIdProche) {
                ++$nbAvecStation;
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d Sanitaire crees, %d rattaches a une Station a moins de %dm.',
            $nbCrees,
            $nbAvecStation,
            self::DISTANCE_MAX_METRES,
        ));

        return Command::SUCCESS;
    }
}
