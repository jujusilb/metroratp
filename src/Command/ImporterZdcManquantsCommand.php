<?php

namespace App\Command;

use App\Entity\Station;
use App\Repository\StationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cree les Station pour les ZdC presentes dans le referentiel officiel IDFM
 * (zones-d-arrets.csv) mais absentes du GTFS actuel, donc jamais importees jusqu'ici (voir
 * documentation/TODO.md, "ZdC absents de zdc_coordonnees.csv" - 1789 ZdC verifies totalement
 * absents de stops.txt, pas juste un bug d'extraction : elles n'ont simplement plus de service
 * programme actuellement, mais restent de vrais arrets officiels).
 *
 * Coordonnees reprojetees depuis Lambert-93 (voir extraire_zdc_manquants_lambert93.php, formule
 * validee a moins de 70m d'ecart contre des ZdC deja connus des deux facons).
 *
 * Ces Station n'auront aucune Desserte/Ligne rattachee (pas de service actif a rattacher) : juste
 * un label, une commune, des coordonnees et leur code_externe - suffisant pour apparaitre sur la
 * carte et dans /ville, mais pas dans le calculateur de trajet.
 *
 * Idempotent : ignore les ZdC qui ont deja une Station (code_externe) en base.
 */
#[AsCommand(name: 'app:importer-zdc-manquants', description: 'Cree les Station pour les ZdC du referentiel officiel absentes du GTFS actuel')]
class ImporterZdcManquantsCommand extends Command
{
    private const CSV = 'documentation/scripts/donnees-extraites/zdc_manquants_lambert93.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StationRepository $stationRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fichier = fopen(self::CSV, 'r');
        $header = fgetcsv($fichier);
        $idx = array_flip($header);

        $nbCrees = 0;
        $nbDejaPresents = 0;
        $compteur = 0;
        while (false !== ($ligne = fgetcsv($fichier))) {
            $zdc = $ligne[$idx['zdc']];

            if (null !== $this->stationRepository->findOneBy(['codeExterne' => $zdc])) {
                ++$nbDejaPresents;
                continue;
            }

            $station = new Station();
            $station->setLabel($ligne[$idx['label']]);
            $station->setVille('' !== $ligne[$idx['commune']] ? $ligne[$idx['commune']] : null);
            $station->setCodeExterne($zdc);
            $station->setLatitude((float) $ligne[$idx['latitude']]);
            $station->setLongitude((float) $ligne[$idx['longitude']]);
            $this->entityManager->persist($station);
            ++$nbCrees;

            if (0 === (++$compteur % 500)) {
                $this->entityManager->flush();
            }
        }
        fclose($fichier);
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d Station créées, %d déjà présentes (code_externe existant, ignorées).',
            $nbCrees,
            $nbDejaPresents,
        ));

        return Command::SUCCESS;
    }
}
