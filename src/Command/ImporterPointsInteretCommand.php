<?php

namespace App\Command;

use App\Entity\PointInteret;
use App\Repository\PointInteretRepository;
use App\Repository\StationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les PointInteret depuis points_interet.csv (voir
 * documentation/scripts/extraire_points_interet.php) et les rattache a leur(s) Station via ZdC
 * (StationRepository::trouverIdCanoniqueParZdc(), meme rattachement que app:construire-positions-rame).
 * Idempotent : upsert par label, recalcule le rattachement Station a chaque execution.
 */
#[AsCommand(name: 'app:importer-points-interet', description: 'Importe les PointInteret (lieux remarquables a proximite d une Station) depuis points_interet.csv')]
class ImporterPointsInteretCommand extends Command
{
    private const CSV = 'documentation/scripts/donnees-extraites/points_interet.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PointInteretRepository $pointInteretRepository,
        private readonly StationRepository $stationRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stationIdParZdc = $this->stationRepository->trouverIdCanoniqueParZdc();
        $io->info(sprintf('%d Stations avec codeExterne.', \count($stationIdParZdc)));

        $fichier = fopen(self::CSV, 'r');
        $header = fgetcsv($fichier);
        $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
        $idx = array_flip($header);

        $nbLieux = 0;
        $nbRattachements = 0;
        $nbSansStation = 0;
        $pointInteretParLabel = []; // cache en memoire : un meme lieu peut apparaitre plusieurs
        // fois dans le CSV (une paire par Station proche) avant le flush() final, donc pas encore
        // requetable en base via findOneBy() entre-temps.
        while (false !== ($ligne = fgetcsv($fichier))) {
            $zdc = $ligne[$idx['zdc']];
            $label = $ligne[$idx['label']];

            $pointInteret = $pointInteretParLabel[$label] ?? $this->pointInteretRepository->findOneBy(['label' => $label]);
            if (null === $pointInteret) {
                $pointInteret = new PointInteret();
                $pointInteret->setLabel($label);
                $this->entityManager->persist($pointInteret);
                ++$nbLieux;
            }
            $pointInteretParLabel[$label] = $pointInteret;

            $stationId = $stationIdParZdc[$zdc] ?? null;
            if (null === $stationId) {
                ++$nbSansStation;
                continue;
            }

            $station = $this->stationRepository->find($stationId);
            if (null !== $station && !$pointInteret->getStations()->contains($station)) {
                $pointInteret->addStation($station);
                ++$nbRattachements;
            }
        }
        fclose($fichier);

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d PointInteret au total, %d rattachements Station créés, %d sans Station trouvée (ZdC inconnue).',
            $this->pointInteretRepository->count([]),
            $nbRattachements,
            $nbSansStation,
        ));

        return Command::SUCCESS;
    }
}
