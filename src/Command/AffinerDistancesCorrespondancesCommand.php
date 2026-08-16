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
 *
 * Repli par nom (cf. "Stations dupliquees", TODO.md) : quand la Station n'a pas de code_externe
 * propre (originale creee avant app:importer-reseau-complet), on cherche une AUTRE Station de
 * meme label qui, elle, a un code_externe — meme lieu reel, doublon connu et documente. On ne
 * l'utilise que si ce label a EXACTEMENT une seule Station candidate avec code_externe : des
 * labels comme "Republique", "Gambetta" ou "Stalingrad" existent dans des dizaines de communes
 * sans rapport (verifie manuellement), un repli sur un label ambigu donnerait un temps de marche
 * pour la mauvaise station. Dans ce cas ambigu, la correspondance reste volontairement NULL.
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

        $io->section('Chargement du repli par nom (label -> code_externe, jumeau non ambigu)...');
        $jumeauParLabel = [];
        foreach ($connexion->executeQuery(
            'SELECT label, code_externe FROM station WHERE code_externe IS NOT NULL'
        )->fetchAllAssociative() as $ligne) {
            $jumeauParLabel[$ligne['label']][] = $ligne['code_externe'];
        }
        $io->info(\count($jumeauParLabel).' labels distincts avec au moins un code_externe.');

        $io->section('Recherche des correspondances a affiner (distance NULL, meme Station)...');
        $aAffiner = $connexion->executeQuery(
            <<<'SQL'
                SELECT c.id, sa.label AS label, sa.code_externe AS code_externe
                FROM correspondance c
                JOIN desserte da ON da.id = c.desserte_a_id
                JOIN desserte db ON db.id = c.desserte_b_id
                JOIN station sa ON sa.id = da.station_id
                WHERE c.distance IS NULL AND da.station_id = db.station_id
                SQL
        )->fetchAllAssociative();
        $io->info(\count($aAffiner).' correspondances candidates (meme Station, distance NULL).');

        $nbAffineesDirect = 0;
        $nbAffineesRepliNom = 0;
        foreach ($aAffiner as $ligne) {
            $duree = null === $ligne['code_externe'] ? null : ($dureeParZdc[$ligne['code_externe']] ?? null);
            $viaRepliNom = false;

            if (null === $duree) {
                $jumeaux = $jumeauParLabel[$ligne['label']] ?? [];
                if (1 === \count($jumeaux)) {
                    $duree = $dureeParZdc[$jumeaux[0]] ?? null;
                    $viaRepliNom = null !== $duree;
                }
            }

            if (null === $duree) {
                continue;
            }

            $distance = (int) round($duree * self::VITESSE_MARCHE_M_PAR_S);
            $connexion->executeStatement(
                'UPDATE correspondance SET distance = ? WHERE id = ?',
                [$distance, $ligne['id']],
            );
            if ($viaRepliNom) {
                ++$nbAffineesRepliNom;
            } else {
                ++$nbAffineesDirect;
            }
        }

        $io->success(sprintf(
            '%d correspondances affinees avec un temps de marche reel (%d via code_externe direct, %d via repli par nom).',
            $nbAffineesDirect + $nbAffineesRepliNom,
            $nbAffineesDirect,
            $nbAffineesRepliNom,
        ));

        return Command::SUCCESS;
    }
}
