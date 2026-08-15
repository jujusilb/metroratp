<?php

namespace App\Command;

use App\Entity\PlanRegion;
use App\Repository\PlanRegionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les PlanRegion (dataset IDFM "plans-region", 19 grandes cartes d'ensemble du reseau :
 * Metro, RER, reseau de Nuit, plans PMR/facile a lire...) depuis plans-region.csv.
 */
#[AsCommand(name: 'app:importer-plans-region', description: 'Importe les PlanRegion depuis plans-region.csv')]
class ImporterPlansRegionCommand extends Command
{
    private const PLANS_CSV = 'documentation/scripts/donnees-extraites/plans-region.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanRegionRepository $planRegionRepository,
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

        $io->section('Import des PlanRegion...');
        $nbCrees = 0;
        $nbMaj = 0;

        foreach ($this->lireCsv(self::PLANS_CSV) as $ligne) {
            $numero = $ligne['ID'];
            $plan = $this->planRegionRepository->trouverParNumero($numero) ?? new PlanRegion();
            $estNouveau = null === $plan->getId();

            $plan->setNumero($numero);
            $plan->setOrdre((int) $ligne["Ordre d'affichage"]);
            $plan->setLabel($ligne['Nom du Plan']);
            $plan->setUrlPdf($ligne['URL']);
            $plan->setUrlFiche($ligne['Vignette'] ?: null);
            $plan->setTailleFichierMo('' !== $ligne['Poids (Mo)'] ? (float) str_replace(',', '.', $ligne['Poids (Mo)']) : null);
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

        $io->success(sprintf('%d PlanRegion crees, %d mis a jour.', $nbCrees, $nbMaj));

        return Command::SUCCESS;
    }
}
