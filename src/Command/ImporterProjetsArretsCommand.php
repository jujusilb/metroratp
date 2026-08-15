<?php

namespace App\Command;

use App\Entity\ProjetArret;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les ProjetArret (dataset IDFM "projets_arrets_idf", ~405 arrets/poles multimodaux en
 * projet ou en construction) depuis projets_arrets_idf.csv.
 *
 * Reconstruction complete a chaque execution (purge puis reimport) : le CSV source n'a pas
 * d'identifiant stable par ligne (un meme ID_PROJET/ID_OPERATI se repete sur plusieurs arrets
 * d'une meme operation), donc pas de cle naturelle fiable pour un find-or-create - meme situation
 * que app:construire-acces-sorties.
 */
#[AsCommand(name: 'app:importer-projets-arrets', description: 'Importe les ProjetArret (reseau en projet/construction) depuis projets_arrets_idf.csv')]
class ImporterProjetsArretsCommand extends Command
{
    private const PROJETS_CSV = 'documentation/scripts/donnees-extraites/projets_arrets_idf.csv';

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Purge des ProjetArret existants...');
        $connexion->executeStatement('DELETE FROM projet_arret');

        $io->section('Lecture de projets_arrets_idf.csv et creation des ProjetArret...');
        $nbCrees = 0;
        $nbIgnores = 0;

        foreach ($this->lireCsv(self::PROJETS_CSV) as $ligne) {
            [$latStr, $lonStr] = array_pad(array_map('trim', explode(',', $ligne['geo_point_2d'])), 2, null);
            if (null === $latStr || null === $lonStr || '' === $latStr || '' === $lonStr) {
                ++$nbIgnores;
                continue;
            }

            $projet = new ProjetArret();
            $projet->setLabel('' !== $ligne['NOM_ARRET'] ? $ligne['NOM_ARRET'] : $ligne['OPERATION']);
            $projet->setNomProjet($ligne['NOM_PROJET']);
            $projet->setOperation('' !== $ligne['OPERATION'] ? $ligne['OPERATION'] : null);
            $projet->setNature('' !== $ligne['NATURE'] ? $ligne['NATURE'] : null);
            $projet->setMode('' !== $ligne['MODE_'] ? $ligne['MODE_'] : null);
            $projet->setStatut('' !== $ligne['STATUT'] ? $ligne['STATUT'] : null);
            $projet->setPhase('' !== $ligne['PHASE'] ? $ligne['PHASE'] : null);
            $projet->setCreation('1' === $ligne['CREATION']);
            $projet->setProlongement('1' === $ligne['PROLONG']);
            $projet->setAmelioration('1' === $ligne['AMELIOR']);
            $projet->setTerminus('1' === $ligne['TERMINUS']);
            $projet->setLatitude((float) $latStr);
            $projet->setLongitude((float) $lonStr);

            $this->entityManager->persist($projet);
            ++$nbCrees;
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d ProjetArret crees (%d ignores : coordonnees manquantes).', $nbCrees, $nbIgnores));

        return Command::SUCCESS;
    }
}
