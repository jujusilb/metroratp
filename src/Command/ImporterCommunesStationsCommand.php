<?php

namespace App\Command;

use App\Repository\StationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renseigne la commune (Station::ville) de toutes les stations ayant un code externe (ZdCId),
 * a partir du CSV brut "zones-de-correspondance.csv" du referentiel IDFM (colonnes ZdCId;...;
 * ZdCName;...;ZdCTown;...). Complement rapide a app:importer-reseau-complet, qui ne recuperait
 * pas encore la commune.
 */
#[AsCommand(name: 'app:importer-communes-stations', description: 'Renseigne la commune des stations ayant un code externe, depuis zones-de-correspondance.csv')]
class ImporterCommunesStationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StationRepository $stationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Chemin de zones-de-correspondance.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $villeParZdc = [];
        $fh = fopen($input->getArgument('fichier'), 'r');
        $header = fgetcsv($fh, separator: ';');
        $idxId = array_search('ZdCId', $header, true);
        $idxTown = array_search('ZdCTown', $header, true);
        while (($row = fgetcsv($fh, separator: ';')) !== false) {
            if (isset($row[$idxTown]) && '' !== $row[$idxTown]) {
                $villeParZdc[$row[$idxId]] = $row[$idxTown];
            }
        }
        fclose($fh);

        $io->writeln(sprintf('Communes chargees depuis le CSV : %d', count($villeParZdc)));

        $nbMisAJour = 0;
        $compteur = 0;
        foreach ($this->stationRepository->findAll() as $station) {
            $code = $station->getCodeExterne();
            if (null === $code || !isset($villeParZdc[$code])) {
                continue;
            }
            $station->setVille($villeParZdc[$code]);
            ++$nbMisAJour;

            if (0 === (++$compteur % 3000)) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d stations mises a jour avec leur commune.', $nbMisAJour));

        return Command::SUCCESS;
    }
}
