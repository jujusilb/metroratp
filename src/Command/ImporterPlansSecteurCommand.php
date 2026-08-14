<?php

namespace App\Command;

use App\Entity\Plan;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les Plan de secteur IDFM depuis plans-de-secteur.csv, puis assigne automatiquement
 * Station::plan quand c'est possible sans ambiguite.
 *
 * L'assignation automatique se limite en pratique a Paris (departement 75) : c'est le seul
 * departement couvert par un seul Plan (numero 3, "Secteur Paris") - tous les departements de
 * grande couronne sont scindes en plusieurs Plan (ex: le 77 en compte 24), donc le simple
 * departement de la Station ne suffit pas a determiner son Plan. Pour les autres stations,
 * Station::plan reste null et doit etre renseigne a la main (formulaire d'edition Station).
 *
 * Le departement d'une Station est deduit de Station::ville :
 * - "Paris Ne" -> 75 directement (arrondissements absents de communes_departements.csv) ;
 * - sinon, correspondance exacte dans communes_departements.csv (extrait de
 *   communes-par-contrat.csv, voir documentation/scripts/extraire_communes_departements.php) -
 *   ignoree si la commune y est associee a plusieurs departements distincts (rare, communes
 *   limitrophes).
 *
 * Un Station.plan deja renseigne (assignation manuelle prealable) n'est jamais ecrase par un
 * nouveau passage de cette commande.
 */
#[AsCommand(name: 'app:importer-plans-secteur', description: 'Importe les Plan de secteur IDFM et assigne automatiquement Station::plan quand non ambigu')]
class ImporterPlansSecteurCommand extends Command
{
    private const PLANS_CSV = 'documentation/scripts/donnees-extraites/plans-de-secteur.csv';
    private const COMMUNES_DEPARTEMENTS_CSV = 'documentation/scripts/donnees-extraites/communes_departements.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanRepository $planRepository,
    ) {
        parent::__construct();
    }

    /**
     * @return \Generator<int, array<string, string>>
     */
    private function lireCsv(string $chemin, string $separateur = ','): \Generator
    {
        $fichier = fopen($chemin, 'r');
        $header = fgetcsv($fichier, separator: $separateur);
        $header[0] = preg_replace('/^\x{FEFF}+/u', '', $header[0]);
        while (false !== ($ligne = fgetcsv($fichier, separator: $separateur))) {
            yield array_combine($header, $ligne);
        }
        fclose($fichier);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Import des Plan de secteur...');
        $deptsParNumero = [];
        $nbCrees = 0;
        $nbMaj = 0;
        foreach ($this->lireCsv(self::PLANS_CSV, ';') as $ligne) {
            $numero = $ligne['Numéro'];
            $depts = array_map('trim', explode(',', $ligne['Département']));
            $deptsParNumero[$numero] = $depts;

            $plan = $this->planRepository->trouverParNumero($numero) ?? new Plan();
            $estNouveau = null === $plan->getId();

            $plan->setNumero($numero);
            $plan->setSecteur($ligne['Secteur']);
            $plan->setDepartements($ligne['Département']);
            $plan->setUrlPdf($ligne['URL']);
            $plan->setUrlFiche($ligne['Photo du plan'] ?: null);
            $plan->setTailleFichierMo('' !== $ligne['Taille du fichier (Mo)'] ? (float) str_replace(',', '.', $ligne['Taille du fichier (Mo)']) : null);
            $plan->setDatePublication($ligne['Date de publication'] ?: null);
            $plan->setFormat($ligne['Format'] ?: null);

            if ($estNouveau) {
                $this->entityManager->persist($plan);
                ++$nbCrees;
            } else {
                ++$nbMaj;
            }
        }
        $this->entityManager->flush();
        $io->info(sprintf('%d Plan crees, %d mis a jour.', $nbCrees, $nbMaj));

        // Departement -> Plan.id, uniquement quand un seul Plan couvre ce departement.
        $planIdParNumero = [];
        foreach ($connexion->executeQuery('SELECT numero, id FROM plan')->iterateAssociative() as $row) {
            $planIdParNumero[$row['numero']] = (int) $row['id'];
        }
        $numerosParDept = [];
        foreach ($deptsParNumero as $numero => $depts) {
            foreach ($depts as $dept) {
                $numerosParDept[$dept][] = $numero;
            }
        }
        $planIdParDeptUnique = [];
        foreach ($numerosParDept as $dept => $numeros) {
            if (1 === \count($numeros)) {
                $planIdParDeptUnique[$dept] = $planIdParNumero[$numeros[0]];
            }
        }
        $io->info(sprintf('Departements couverts par un seul Plan (assignation auto possible) : %s', implode(', ', array_keys($planIdParDeptUnique))));

        $io->section('Chargement commune -> departement...');
        $deptsParCommune = [];
        foreach ($this->lireCsv(self::COMMUNES_DEPARTEMENTS_CSV) as $ligne) {
            $deptsParCommune[$ligne['commune']][$ligne['departement']] = true;
        }
        $io->info(sprintf('%d communes chargees.', \count($deptsParCommune)));

        $io->section('Assignation automatique de Station::plan (departements non ambigus uniquement)...');
        $nbAssignes = 0;
        $nbEnAttente = 0;
        foreach ($connexion->executeQuery('SELECT id, ville FROM station WHERE plan_id IS NULL AND ville IS NOT NULL')->iterateAssociative() as $row) {
            $ville = $row['ville'];

            if (str_starts_with($ville, 'Paris')) {
                $dept = '75';
            } else {
                $depts = array_keys($deptsParCommune[$ville] ?? []);
                $dept = 1 === \count($depts) ? $depts[0] : null;
            }

            if (null === $dept || !isset($planIdParDeptUnique[$dept])) {
                continue;
            }

            $connexion->executeStatement('UPDATE station SET plan_id = ? WHERE id = ?', [$planIdParDeptUnique[$dept], $row['id']]);
            ++$nbAssignes;

            if (++$nbEnAttente >= 500) {
                $nbEnAttente = 0;
                $io->write('.');
            }
        }
        $io->newLine();

        $io->success(sprintf('%d Stations assignees automatiquement a leur Plan de secteur.', $nbAssignes));

        return Command::SUCCESS;
    }
}
