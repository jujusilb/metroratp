<?php

namespace App\Command;

use App\Entity\HoraireLigne;
use App\Entity\Ligne;
use App\Repository\LigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe HoraireLigne depuis horaires_lignes.csv (voir
 * documentation/COOK/scripts/extraire_horaires_lignes.php pour la methode de calcul), en
 * rattachant chaque ligne du CSV a une Ligne via Ligne::codeExterne (= routeId GTFS). Purge +
 * reimport complet a chaque execution (pas de cle metier plus fine a preserver).
 *
 * Plusieurs Ligne peuvent partager le meme codeExterne dans de rares cas (id non unique en base
 * de test), mais codeExterne est UNIQUE en production (contrainte d'entite) : on applique
 * l'horaire a toutes les Ligne trouvees pour ce code, par simplicite.
 */
#[AsCommand(name: 'app:importer-horaires-lignes', description: 'Importe HoraireLigne depuis horaires_lignes.csv (premier/dernier depart par ligne et type de jour)')]
class ImporterHorairesLignesCommand extends Command
{
    private const HORAIRES_CSV = 'documentation/COOK/scripts/donnees-extraites/horaires_lignes.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LigneRepository $ligneRepository,
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
        $header[0] = preg_replace('/^\x{FEFF}+/u', '', $header[0]);
        while (false !== ($ligne = fgetcsv($fichier))) {
            yield array_combine($header, $ligne);
        }
        fclose($fichier);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->entityManager->getRepository(HoraireLigne::class)->findAll() as $horaire) {
            $this->entityManager->remove($horaire);
        }
        $this->entityManager->flush();

        $lignesParCodeExterne = [];
        foreach ($this->ligneRepository->findAll() as $ligne) {
            $code = $ligne->getCodeExterne();
            if (null !== $code) {
                $lignesParCodeExterne[$code][] = $ligne;
            }
        }

        $nbImportes = 0;
        $nbSansLigne = 0;
        foreach ($this->lireCsv(self::HORAIRES_CSV) as $row) {
            $lignes = $lignesParCodeExterne[$row['routeId']] ?? [];
            if ([] === $lignes) {
                ++$nbSansLigne;
                continue;
            }

            foreach ($lignes as $ligne) {
                $horaire = new HoraireLigne();
                $horaire->setLigne($ligne);
                $horaire->setTypeJour($row['typeJour']);
                $horaire->setPremierDepart(new \DateTime($row['premierDepart']));
                $horaire->setDernierDepart(new \DateTime($row['dernierDepart']));
                $this->entityManager->persist($horaire);
                ++$nbImportes;
            }
        }

        $this->entityManager->flush();

        $io->success("$nbImportes HoraireLigne importes ($nbSansLigne lignes du CSV sans Ligne correspondante en base).");

        return Command::SUCCESS;
    }
}
