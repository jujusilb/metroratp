<?php

namespace App\Command;

use App\Repository\StationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplit Station::accessibilitePmr/accessibilitePmrCommentaire depuis accessibilite_gares.csv
 * (extrait d'accessibilite-en-gare.csv resolu vers la ZdC via stops.txt - voir
 * documentation/scripts/extraire_accessibilite_gares.php ; le feed GTFS complet n'est jamais
 * commit). Rattachement par StationRepository::trouverIdCanoniqueParZdc() (comme
 * app:construire-acces-sorties) : la donnee doit atterrir sur la Station "originale" que
 * /station/{id} affiche reellement, pas sur son eventuel doublon ZdC-lie (voir TODO.md).
 *
 * Grain "gare" (459 gares environ, principalement train/RER/metro) : la grande majorite des
 * Station (arrets de bus) n'auront jamais cette info, ce qui est fidele a la donnee source (pas
 * une absence d'import).
 */
#[AsCommand(name: 'app:importer-accessibilite-gares', description: 'Importe le niveau d\'accessibilite PMR des gares depuis accessibilite_gares.csv')]
class ImporterAccessibiliteGaresCommand extends Command
{
    private const ACCESSIBILITE_CSV = 'documentation/scripts/donnees-extraites/accessibilite_gares.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StationRepository $stationRepository,
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Chargement des Stations connues (par codeExterne / ZdC)...');
        $stationIdParZdc = $this->stationRepository->trouverIdCanoniqueParZdc();
        $io->info(\count($stationIdParZdc).' Stations avec un codeExterne.');

        $io->section('Lecture de accessibilite_gares.csv et mise a jour des Station...');
        $nbMaj = 0;
        $nbSansStation = 0;

        foreach ($this->lireCsv(self::ACCESSIBILITE_CSV) as $ligne) {
            $stationId = $stationIdParZdc[$ligne['zdc']] ?? null;
            if (null === $stationId) {
                ++$nbSansStation;
                continue;
            }

            $connexion->executeStatement(
                'UPDATE station SET accessibilite_pmr = ?, accessibilite_pmr_commentaire = ? WHERE id = ?',
                [$ligne['niveau'], '' !== $ligne['commentaire'] ? $ligne['commentaire'] : null, $stationId],
            );
            ++$nbMaj;
        }

        $io->success(sprintf('%d Stations mises a jour avec leur accessibilite PMR (%d ignorees : ZdC sans Station en base).', $nbMaj, $nbSansStation));

        return Command::SUCCESS;
    }
}
