<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
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
 * Importe les durees reelles de troncon calculees a partir des horaires theoriques GTFS IDFM
 * (fichier CSV genere par un script externe, colonnes : stop_id_a,nom_a,stop_id_b,nom_b,duree_moyenne_secondes,nb_observations).
 *
 * Le nom de station est la seule cle de rapprochement fiable disponible (le GTFS n'a pas
 * d'identifiant commun avec notre base) : on normalise les deux cotes (accents, tirets,
 * casse) et on applique un petit dictionnaire pour les quelques variantes de nommage
 * restantes (parentheses, mots en plus/en moins).
 *
 * Le CSV source distingue deja les deux sens de circulation (chaque stop_id de depart a son
 * propre stop_id d'arrivee) : 661 des 772 paires ont un aller et un retour presents ET
 * DIFFERENTS (ex: Liege sur la ligne 13 metro, 89s vers Saint-Lazare mais 65s vers Clichy - vrai
 * quai decale, pas juste un artefact d'arrondi). Jusqu'ici cette nuance etait perdue : seule une
 * valeur (le premier sens trouve, ou son inverse a defaut) etait appliquee symetriquement aux
 * DEUX sens via Troncon::dureeReelleSecondes. Ecrit desormais aussi sur
 * TronconDesserte::dureeReelleSecondes (role Depart), un par sens reel - Troncon::dureeReelleSecondes
 * reste rempli en repli (utilise par TrajetFinder si un sens precis manque, voir sa docblock).
 */
#[AsCommand(name: 'app:importer-durees-troncon', description: 'Importe les durees reelles de troncon depuis un CSV GTFS pre-calcule')]
class ImporterDureesTronconCommand extends Command
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
            ->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du CSV troncon_durees.csv')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'N\'ecrit rien en base, affiche seulement le rapport')
        ;
    }

    private function trouverTronconDesserteDepart(Troncon $troncon, Desserte $depart): ?TronconDesserte
    {
        foreach ($troncon->getTronconDessertes() as $tronconDesserte) {
            if ('Départ' === $tronconDesserte->getTypeDesserte()?->getLabel() && $tronconDesserte->getDesserte() === $depart) {
                return $tronconDesserte;
            }
        }

        return null;
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

        // duree[nomNormaliseA][nomNormaliseB] = ['secondes' => int, 'observations' => int]
        $durees = [];
        $fh = fopen($input->getArgument('fichier'), 'r');
        fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            [, $nomA, , $nomB, $secondes, $observations] = $row;
            $a = self::CORRESPONDANCES_MANUELLES[$this->normaliser($nomA)] ?? $this->normaliser($nomA);
            $b = self::CORRESPONDANCES_MANUELLES[$this->normaliser($nomB)] ?? $this->normaliser($nomB);
            $durees[$a][$b] = ['secondes' => (int) $secondes, 'observations' => (int) $observations];
        }
        fclose($fh);
        $io->writeln(sprintf('Paires de durees chargees depuis le CSV : %d', array_sum(array_map('count', $durees))));

        $ids = $this->tronconRepository->findIdsTousTroncons();
        $matches = 0;
        $sansCorrespondance = [];
        $sensAsymetriques = 0;

        // Par lot (jamais tout le reseau en memoire d'un coup, voir le docblock de la methode de
        // repository) : chaque lot est traite, flushe puis l'EntityManager est vide avant le
        // suivant - aucune donnee du lot precedent n'est necessaire pour traiter celui d'apres
        // (chaque Troncon est autonome, la boucle interne ne lit/ecrit que sur lui-meme).
        foreach (array_chunk($ids, 1000) as $lot) {
            $troncons = $this->tronconRepository->trouverAvecDetailsSimplifiesParIds($lot);

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

                $trouve = $durees[$nomA][$nomB] ?? $durees[$nomB][$nomA] ?? null;
                if (null === $trouve) {
                    $sansCorrespondance[] = "$stationA -> $stationB";
                    continue;
                }

                ++$matches;
                if (!$dryRun) {
                    // Repli symetrique (utilise par TrajetFinder si un sens precis manque ci-dessous).
                    $troncon->setDureeReelleSecondes($trouve['secondes']);
                }

                // Par-dessus le repli : un sens precis (asymetrique) par TronconDesserte Depart,
                // quand le CSV a bien ce sens exact (pas juste son inverse).
                foreach ($sens as $unSens) {
                    if (null === $unSens['depart'] || null === $unSens['arrivee']) {
                        continue;
                    }
                    $nomDepart = $this->normaliser($unSens['depart']->getStation()?->getLabel() ?? '');
                    $nomArrivee = $this->normaliser($unSens['arrivee']->getStation()?->getLabel() ?? '');
                    $trouveExact = $durees[$nomDepart][$nomArrivee] ?? null;
                    if (null === $trouveExact) {
                        continue;
                    }

                    $tronconDesserteDepart = $this->trouverTronconDesserteDepart($troncon, $unSens['depart']);
                    if (null === $tronconDesserteDepart) {
                        continue;
                    }

                    if (!$dryRun) {
                        $tronconDesserteDepart->setDureeReelleSecondes($trouveExact['secondes']);
                    }
                    ++$sensAsymetriques;
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            }
            $this->entityManager->clear();
        }

        $io->success(sprintf(
            '%d / %d troncons avec une duree reelle trouvee, dont %d sens precis (asymetriques ou non) sur TronconDesserte (%s)',
            $matches,
            count($ids),
            $sensAsymetriques,
            $dryRun ? 'dry-run, rien ecrit' : 'ecrit en base',
        ));

        if (count($sansCorrespondance) > 0) {
            $io->section(sprintf('%d troncons sans correspondance GTFS (garderont le poids fixe par defaut) :', count($sansCorrespondance)));
            foreach ($sansCorrespondance as $ligne) {
                $io->writeln("  - $ligne");
            }
        }

        return Command::SUCCESS;
    }
}
