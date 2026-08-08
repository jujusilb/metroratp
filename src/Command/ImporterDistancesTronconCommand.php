<?php

namespace App\Command;

use App\Repository\TronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les distances reelles de troncon calculees a partir du trace geographique GTFS IDFM
 * (fichier CSV genere par un script externe, colonnes :
 * stop_id_a,nom_a,stop_id_b,nom_b,distance_metres,nb_observations).
 *
 * Meme logique de rapprochement par nom de station (normalise + dictionnaire manuel) que
 * app:importer-durees-troncon, puisque le GTFS n'a pas d'identifiant commun avec notre base.
 * Contrairement a la duree, la distance est une propriete physique fixe du troncon : elle ne
 * depend pas du materiel roulant qui y circule.
 */
#[AsCommand(name: 'app:importer-distances-troncon', description: 'Importe les distances reelles de troncon depuis un CSV GTFS pre-calcule')]
class ImporterDistancesTronconCommand extends Command
{
    private const CORRESPONDANCES_MANUELLES = [
        'chevilly larue marche international' => 'chevilly larue',
        'javel andre citroen' => 'javel parc andre citroen',
        'pont marie cite des arts' => 'pont marie',
        'saint paul le marais' => 'saint paul',
        'thiais orly pont de rungis' => 'thiais orly',
        'asnieres gennevilliers les courtilles' => 'les courtilles',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TronconRepository $tronconRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du CSV troncon_distances.csv')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'N\'ecrit rien en base, affiche seulement le rapport')
        ;
    }

    private function normaliser(string $label): string
    {
        $label = str_replace(['—', '–'], '-', $label);
        $label = \Normalizer::normalize($label, \Normalizer::FORM_D);
        $label = preg_replace('/\p{Mn}/u', '', $label);
        $label = strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label);

        return trim($label);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        // distances[nomNormaliseA][nomNormaliseB] = ['metres' => float, 'observations' => int]
        $distances = [];
        $fh = fopen($input->getArgument('fichier'), 'r');
        fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            [, $nomA, , $nomB, $metres, $observations] = $row;
            $a = self::CORRESPONDANCES_MANUELLES[$this->normaliser($nomA)] ?? $this->normaliser($nomA);
            $b = self::CORRESPONDANCES_MANUELLES[$this->normaliser($nomB)] ?? $this->normaliser($nomB);
            $distances[$a][$b] = ['metres' => (float) $metres, 'observations' => (int) $observations];
        }
        fclose($fh);
        $io->writeln(sprintf('Paires de distances chargees depuis le CSV : %d', array_sum(array_map('count', $distances))));

        $troncons = $this->tronconRepository->findAllWithDetails();
        $matches = 0;
        $sansCorrespondance = [];

        foreach ($troncons as $troncon) {
            $sens = $troncon->getSensCirculation();
            $premier = $sens[0] ?? null;
            if (null === $premier || null === $premier['depart'] || null === $premier['arrivee']) {
                continue;
            }

            $stationA = $premier['depart']->getStation()?->getLabel();
            $stationB = $premier['arrivee']->getStation()?->getLabel();
            if (null === $stationA || null === $stationB) {
                continue;
            }

            $nomA = $this->normaliser($stationA);
            $nomB = $this->normaliser($stationB);

            $trouve = $distances[$nomA][$nomB] ?? $distances[$nomB][$nomA] ?? null;
            if (null === $trouve) {
                $sansCorrespondance[] = "$stationA -> $stationB";
                continue;
            }

            $matches++;
            if (!$dryRun) {
                $troncon->setDistance($trouve['metres']);
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('%d / %d troncons avec une distance reelle trouvee (%s)', $matches, count($troncons), $dryRun ? 'dry-run, rien ecrit' : 'ecrit en base'));

        if (count($sansCorrespondance) > 0) {
            $io->section(sprintf('%d troncons sans correspondance GTFS (distance restera vide) :', count($sansCorrespondance)));
            foreach ($sansCorrespondance as $ligne) {
                $io->writeln("  - $ligne");
            }
        }

        return Command::SUCCESS;
    }
}
