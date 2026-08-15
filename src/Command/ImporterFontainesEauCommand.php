<?php

namespace App\Command;

use App\Entity\Acces;
use App\Entity\FontaineEau;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les FontaineEau (dataset IDFM "fontaines-a-eau-dans-le-reseau-ratp", 91 emplacements
 * avec coordonnees exploitables sur 93). Aucune cle stable par ligne dans le CSV source : purge +
 * reimport complet a chaque execution (comme app:importer-sanitaires/app:importer-defibrillateurs).
 *
 * Contrairement aux autres datasets d'equipements en station, la colonne "id IDM de l'acces le
 * plus proche" correspond exactement a Acces::codeExterne (verifie avant de coder : 91/91
 * rattaches) : le rattachement a Acces est donc OFFICIEL. La Station est ensuite derivee via les
 * Sortie de cet Acces, pour un affichage direct sur la fiche Station.
 */
#[AsCommand(name: 'app:importer-fontaines-eau', description: 'Importe les FontaineEau et les rattache officiellement via Acces::codeExterne')]
class ImporterFontainesEauCommand extends Command
{
    private const FONTAINES_CSV = 'documentation/scripts/donnees-extraites/fontaines-a-eau-dans-le-reseau-ratp.csv';

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

    /**
     * "50147895.0" -> "50147895" (le CSV source encode ces identifiants comme des flottants).
     */
    private function normaliserId(string $valeur): ?string
    {
        $valeur = trim($valeur);
        if ('' === $valeur) {
            return null;
        }

        return (string) (int) (float) $valeur;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Chargement des Acces (codeExterne) et de leur Station via Sortie...');
        $accesParCode = [];
        foreach ($connexion->executeQuery('SELECT id, code_externe FROM acces WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $accesParCode[$row['code_externe']] = (int) $row['id'];
        }
        $stationParAcces = [];
        foreach ($connexion->executeQuery('SELECT acces_id, station_id FROM sortie WHERE acces_id IS NOT NULL AND station_id IS NOT NULL')->iterateAssociative() as $row) {
            $stationParAcces[(int) $row['acces_id']] ??= (int) $row['station_id'];
        }
        $io->info(\count($accesParCode).' Acces avec codeExterne, '.\count($stationParAcces).' relies a une Station via Sortie.');

        $io->section('Purge des FontaineEau existants...');
        $connexion->executeStatement('DELETE FROM fontaine_eau');

        $io->section('Lecture de fontaines-a-eau-dans-le-reseau-ratp.csv et creation des FontaineEau...');
        $nbCrees = 0;
        $nbAvecAcces = 0;
        $nbAvecStation = 0;

        foreach ($this->lireCsv(self::FONTAINES_CSV) as $ligne) {
            if ('' === $ligne['Latitude'] || '' === $ligne['Longitude']) {
                continue;
            }

            $idAcces = $this->normaliserId($ligne["id IDM de l'accès le plus proche"]);
            $accesId = null !== $idAcces ? ($accesParCode[$idAcces] ?? null) : null;
            $stationId = null !== $accesId ? ($stationParAcces[$accesId] ?? null) : null;

            $fontaine = new FontaineEau();
            $fontaine->setLigneLabel('' !== $ligne['Ligne'] ? $ligne['Ligne'] : null);
            $fontaine->setLabel($ligne['Station ou Gare']);
            $fontaine->setAdresse('' !== $ligne['Adresse'] ? $ligne['Adresse'] : null);
            $fontaine->setCodePostal('' !== $ligne['Code postal'] ? $ligne['Code postal'] : null);
            $fontaine->setCommune('' !== $ligne['Commune'] ? $ligne['Commune'] : null);
            $fontaine->setNumeroAccesProche($this->normaliserId($ligne["Numéro de l'accès à la station le plus proche "]));
            $fontaine->setNomAccesProche('' !== trim($ligne["Nom de l'accès à la station le plus proche \n"]) ? trim($ligne["Nom de l'accès à la station le plus proche \n"]) : null);
            $fontaine->setEnZoneControlee('' !== $ligne['En zone contrôlée ou non'] ? $ligne['En zone contrôlée ou non'] : null);
            $fontaine->setIdentifiantRatp($this->normaliserId($ligne["Identifiant \nRATP"]));
            $fontaine->setLatitude((float) $ligne['Latitude']);
            $fontaine->setLongitude((float) $ligne['Longitude']);
            $fontaine->setAcces(null !== $accesId ? $this->entityManager->getReference(Acces::class, $accesId) : null);
            $fontaine->setStation(null !== $stationId ? $this->entityManager->getReference(Station::class, $stationId) : null);

            $this->entityManager->persist($fontaine);
            ++$nbCrees;
            if (null !== $accesId) {
                ++$nbAvecAcces;
            }
            if (null !== $stationId) {
                ++$nbAvecStation;
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d FontaineEau crees, %d rattaches a un Acces officiel, %d rattaches a une Station.',
            $nbCrees,
            $nbAvecAcces,
            $nbAvecStation,
        ));

        return Command::SUCCESS;
    }
}
