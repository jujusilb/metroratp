<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplit Ligne::trace depuis traces_lignes.csv (extrait de traces-des-lignes-de-transport-en-
 * commun-idfm.csv + simplification Douglas-Peucker - voir
 * documentation/scripts/extraire_traces_lignes.php ; le feed source, 76 Mo, n'est jamais commit).
 *
 * Rattachement par codeExterne pour bus/RER/tram (fiable), par LABEL pour le metro : meme
 * contournement que app:construire-positions-rame, le codeExterne stocke sur les Ligne de metro
 * etant incoherent avec le GTFS actuel (voir TODO.md). Le dataset source couvre tous les modes
 * (bus/metro/RER/tram/funiculaire/telepherique), sans ambiguite de label dans le perimetre metro
 * (16 lignes, noms uniques).
 */
#[AsCommand(name: 'app:importer-traces-lignes', description: 'Importe le trace geometrique reel de chaque Ligne depuis traces_lignes.csv')]
class ImporterTracesLignesCommand extends Command
{
    private const TRACES_CSV = 'documentation/scripts/donnees-extraites/traces_lignes.csv';
    private const TAILLE_LOT = 200;

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Chargement des Lignes (par codeExterne, avec repli par label pour le metro)...');
        $ligneIdParCode = [];
        foreach ($connexion->executeQuery('SELECT code_externe, id FROM ligne WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $ligneIdParCode[$row['code_externe']] = (int) $row['id'];
        }
        $ligneIdParLabel = [];
        foreach ($connexion->executeQuery('SELECT UPPER(label) AS label, id, code_externe FROM ligne ORDER BY (code_externe IS NULL) DESC')->iterateAssociative() as $row) {
            if (!isset($ligneIdParLabel[$row['label']])) {
                $ligneIdParLabel[$row['label']] = (int) $row['id'];
            }
        }
        $io->info(sprintf('%d Lignes avec codeExterne, %d labels de Ligne.', \count($ligneIdParCode), \count($ligneIdParLabel)));

        $io->section('Lecture de traces_lignes.csv et mise a jour des Ligne...');
        $nbMaj = 0;
        $nbEnAttente = 0;
        $nbIgnores = 0;

        foreach ($this->lireCsv(self::TRACES_CSV) as $ligne) {
            $ligneId = $ligneIdParCode[$ligne['codeExterne']] ?? $ligneIdParLabel[mb_strtoupper($ligne['label'])] ?? null;
            if (null === $ligneId) {
                ++$nbIgnores;
                continue;
            }

            $connexion->executeStatement('UPDATE ligne SET trace = ? WHERE id = ?', [$ligne['coordonnees'], $ligneId]);
            ++$nbMaj;

            if (++$nbEnAttente >= self::TAILLE_LOT) {
                $nbEnAttente = 0;
                $io->write('.');
            }
        }
        $io->newLine();

        $io->success(sprintf('%d Lignes mises a jour avec leur trace reel (%d ignorees : aucune Ligne correspondante).', $nbMaj, $nbIgnores));

        return Command::SUCCESS;
    }
}
