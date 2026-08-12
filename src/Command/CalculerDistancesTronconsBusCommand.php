<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplit Troncon::distance pour les troncons de bus (actuellement tous a NULL : la duree l'est
 * deja, voir app:construire-topologie-bus, mais aucune commande n'avait jamais calcule de
 * distance pour ce mode - seul le metro en a via un CSV externe pre-calcule,
 * app:importer-distances-troncon).
 *
 * A la difference du metro (trace GTFS shapes.txt), on utilise ici la distance a vol d'oiseau
 * entre les coordonnees des deux Zones de correspondance (zdc_coordonnees.csv, extrait de
 * stops.txt GTFS IDFM location_type=1 - voir documentation/scripts/extraire_coordonnees_zdc.php ;
 * le feed GTFS complet, ~1,3 Go, n'est jamais commit) : une approximation, mais raisonnable pour
 * du bus (arrets consecutifs rapproches, quelques centaines de metres en general) - a ne pas
 * reutiliser tel quel pour RER/Tram, ou l'ecart avec le trace reel des rails peut etre plus
 * important sur de longues distances entre gares.
 *
 * Ne touche qu'aux troncons de bus dont distance est encore NULL (rejouable sans ecraser une
 * future distance plus precise saisie autrement).
 */
#[AsCommand(name: 'app:calculer-distances-troncons-bus', description: 'Calcule la distance a vol d\'oiseau des troncons de bus depuis les coordonnees ZdC (GTFS)')]
class CalculerDistancesTronconsBusCommand extends Command
{
    private const ZDC_COORDONNEES_CSV = 'documentation/scripts/donnees-extraites/zdc_coordonnees.csv';

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
        $header = fgetcsv($fichier);
        while (false !== ($ligne = fgetcsv($fichier))) {
            yield array_combine($header, $ligne);
        }
        fclose($fichier);
    }

    private function distanceMetres(float $lonA, float $latA, float $lonB, float $latB): float
    {
        $dLat = ($latB - $latA) * 111320;
        $dLon = ($lonB - $lonA) * 111320 * cos(deg2rad(($latA + $latB) / 2));

        return sqrt($dLat ** 2 + $dLon ** 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Chargement des coordonnees ZdC (zdc_coordonnees.csv)...');
        $coordsParZdc = [];
        foreach ($this->lireCsv(self::ZDC_COORDONNEES_CSV) as $ligne) {
            $coordsParZdc[$ligne['zdc']] = [(float) $ligne['longitude'], (float) $ligne['latitude']];
        }
        $io->info(\count($coordsParZdc).' ZdC avec coordonnees.');

        $io->section('Chargement des ZdC touches par chaque troncon de bus sans distance...');
        $zdcParTroncon = [];
        foreach ($connexion->executeQuery(
            <<<'SQL'
                SELECT t.id AS troncon_id, s.code_externe AS zdc
                FROM troncon t
                JOIN troncon_desserte td ON td.troncon_id = t.id
                JOIN desserte d ON d.id = td.desserte_id
                JOIN station s ON s.id = d.station_id
                JOIN ligne l ON l.id = d.ligne_id
                JOIN type_transport tt ON tt.id = l.type_transport_id
                WHERE tt.label = 'Bus' AND t.distance IS NULL AND s.code_externe IS NOT NULL
                GROUP BY t.id, s.code_externe
                SQL
        )->iterateAssociative() as $row) {
            $zdcParTroncon[(int) $row['troncon_id']][] = $row['zdc'];
        }
        $io->info(\count($zdcParTroncon).' troncons de bus sans distance.');

        $io->section('Calcul et mise a jour...');
        $nbMaj = 0;
        $nbIgnores = 0;
        foreach ($zdcParTroncon as $tronconId => $zdcs) {
            $zdcs = array_unique($zdcs);
            if (2 !== \count($zdcs)) {
                ++$nbIgnores;
                continue;
            }
            [$zdcA, $zdcB] = array_values($zdcs);
            if (!isset($coordsParZdc[$zdcA], $coordsParZdc[$zdcB])) {
                ++$nbIgnores;
                continue;
            }

            [$lonA, $latA] = $coordsParZdc[$zdcA];
            [$lonB, $latB] = $coordsParZdc[$zdcB];
            $distance = $this->distanceMetres($lonA, $latA, $lonB, $latB);

            $connexion->executeStatement('UPDATE troncon SET distance = ? WHERE id = ?', [$distance, $tronconId]);
            ++$nbMaj;
        }

        $io->success(sprintf('%d troncons de bus mis a jour avec une distance (%d ignores : ZdC introuvable ou ambigu).', $nbMaj, $nbIgnores));

        return Command::SUCCESS;
    }
}
