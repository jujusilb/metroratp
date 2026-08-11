<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplace l'estimation par defaut (distance NULL, TrajetFinder retombe alors sur 3 min fixes)
 * des Correspondance existantes par un temps de marche reel, quand transfers.txt (GTFS IDFM) en
 * fournit un pour la Station concernee — documentation/scripts/extraire_temps_marche_intra_zdc.php.
 *
 * Ne touche qu'aux Correspondance dont desserteA et desserteB partagent la MEME Station (le cas
 * de toutes celles creees par ConstruireCorrespondancesInterModesCommand) et dont distance est
 * encore NULL : ne remplace jamais une valeur deja saisie (ex: le cas documente Liege 4<->14 a
 * une distance differente selon le quai, une donnee verifiee qu'il ne faut pas ecraser par une
 * mediane generique).
 */
#[AsCommand(name: 'app:affiner-distances-correspondances', description: 'Remplace l\'estimation par defaut des correspondances existantes par un temps de marche reel (GTFS)')]
class AffinerDistancesCorrespondancesCommand extends Command
{
    private const TEMPS_MARCHE_CSV = 'documentation/scripts/donnees-extraites/temps_marche_intra_zdc.csv';
    private const VITESSE_MARCHE_M_PAR_S = 0.9;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Chargement des temps de marche mediane par ZdC...');
        $dureeParZdc = [];
        $fichier = fopen(self::TEMPS_MARCHE_CSV, 'r');
        fgetcsv($fichier);
        while (false !== ($ligne = fgetcsv($fichier))) {
            [$zdc, $dureeSecondes] = $ligne;
            $dureeParZdc[$zdc] = (int) $dureeSecondes;
        }
        fclose($fichier);
        $io->info(\count($dureeParZdc).' ZdC avec un temps de marche connu.');

        $io->section('Recherche des correspondances a affiner (distance NULL, meme Station)...');
        $aAffiner = $connexion->executeQuery(
            <<<'SQL'
                SELECT c.id, sa.code_externe AS code_externe
                FROM correspondance c
                JOIN desserte da ON da.id = c.desserte_a_id
                JOIN desserte db ON db.id = c.desserte_b_id
                JOIN station sa ON sa.id = da.station_id
                WHERE c.distance IS NULL AND da.station_id = db.station_id AND sa.code_externe IS NOT NULL
                SQL
        )->fetchAllAssociative();
        $io->info(\count($aAffiner).' correspondances candidates (meme Station, distance NULL).');

        $nbAffinees = 0;
        foreach ($aAffiner as $ligne) {
            $duree = $dureeParZdc[$ligne['code_externe']] ?? null;
            if (null === $duree) {
                continue;
            }
            $distance = (int) round($duree * self::VITESSE_MARCHE_M_PAR_S);
            $connexion->executeStatement(
                'UPDATE correspondance SET distance = ? WHERE id = ?',
                [$distance, $ligne['id']],
            );
            ++$nbAffinees;
        }

        $io->success(sprintf('%d correspondances affinees avec un temps de marche reel.', $nbAffinees));

        return Command::SUCCESS;
    }
}
