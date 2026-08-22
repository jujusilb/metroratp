<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Corrige les Station dont le code_externe est PERIME (absent du referentiel GTFS actuel,
 * zdc_coordonnees.csv) - decouvert le 2026-08-22 en investiguant "Hôtel de Ville" (Métro 1/11,
 * aucune Sortie/accessibilite/coordonnees) : son code_externe (59762) n'existe plus dans le GTFS,
 * ce qui fait echouer TOUS les imports par code_externe (coordonnees, Sortie, accessibilite...).
 * Meme symptome que Ligne::codeExterne trouve le 2026-08-17, mais sur Station cette fois.
 *
 * Contrairement a app:fusionner-stations-dupliquees (qui ne considere que les Station SANS
 * code_externe), celles-ci EN ONT un, juste invalide - jamais detectees par ce filtre, jamais
 * fusionnees avec leur vraie jumelle ZdC-liee. Seulement 14 cas trouves sur ~13710 Station avec
 * code_externe (verifie exhaustivement contre zdc_coordonnees.csv).
 *
 * Ces 14 stations (des noms tres courants : "Concorde", "Villiers", "Hôtel de Ville"...) n'ont
 * elles-memes pas de coordonnees (meme cause racine), donc pas moyen de les departager par
 * distance directe entre candidats homonymes comme app:fusionner-stations-dupliquees. A la place :
 * chaque station a un voisin Troncon (sur la meme Ligne) deja correctement positionne - le bon
 * candidat homonyme est systematiquement le plus proche de ce voisin, avec une marge enorme (moins
 * de 700m pour 13 des 14, 3.5km pour la derniere, contre plusieurs km a plusieurs dizaines de km
 * pour le 2e candidat le plus proche a chaque fois - verifie avant d'ecrire cette commande).
 *
 * Meme mecanique de fusion que app:fusionner-stations-dupliquees (COALESCE des champs manquants,
 * repointage des 9 tables a FK directe, suppression de la jumelle), a la difference que
 * code_externe est ici FORCE (pas juste complete) puisque la valeur existante est fausse, pas
 * absente - ecrit en dernier, une fois la jumelle supprimee, pour la meme raison de contrainte
 * d'unicite transitoire que la commande sœur.
 */
#[AsCommand(name: 'app:corriger-code-externe-perime', description: 'Corrige les Station dont le code_externe ne correspond plus a aucun ZdC actuel, en les fusionnant avec leur vraie jumelle')]
class CorrigerCodeExterneStationsPerimeCommand extends Command
{
    private const ZDC_COORDONNEES_CSV = 'documentation/scripts/donnees-extraites/zdc_coordonnees.csv';
    private const DISTANCE_MAX_METRES = 5000.0;

    /** @var string[] tables avec une colonne station_id a repointer */
    private const TABLES_STATION_ID = [
        'desserte',
        'sortie',
        'equipement_arret',
        'position_rame',
        'defibrillateur',
        'fontaine_eau',
        'point_de_vente',
        'sanisette_publique',
        'sanitaire',
    ];

    public function __construct(
        private readonly Connection $connexion,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, "N'ecrit rien en base, affiche seulement le rapport");
    }

    private function haversine(float $latA, float $lonA, float $latB, float $lonB): float
    {
        $rayon = 6371000.0;
        $phi1 = deg2rad($latA);
        $phi2 = deg2rad($latB);
        $dPhi = deg2rad($latB - $latA);
        $dLambda = deg2rad($lonB - $lonA);
        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

        return $rayon * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return string[] code_externe valides (presents dans le GTFS actuel)
     */
    private function chargerZdcValides(): array
    {
        $valides = [];
        $fichier = fopen(self::ZDC_COORDONNEES_CSV, 'r');
        fgetcsv($fichier);
        while (false !== ($ligne = fgetcsv($fichier))) {
            $valides[$ligne[0]] = true;
        }
        fclose($fichier);

        return $valides;
    }

    /**
     * @return array<int, array{stationId: int, label: string, zdcId: int, zdcCodeExterne: string, zdcVille: ?string, distance: float}>
     */
    private function trouverCorrections(SymfonyStyle $io): array
    {
        $zdcValides = $this->chargerZdcValides();

        $corrections = [];
        foreach ($this->connexion->executeQuery(
            'SELECT id, label, code_externe FROM station WHERE code_externe IS NOT NULL'
        )->iterateAssociative() as $station) {
            if (isset($zdcValides[$station['code_externe']])) {
                continue;
            }

            $voisin = $this->connexion->executeQuery(
                <<<'SQL'
                    SELECT sv.latitude, sv.longitude
                    FROM desserte d
                    JOIN troncon_desserte td ON td.desserte_id = d.id
                    JOIN troncon_desserte autre ON autre.troncon_id = td.troncon_id AND autre.desserte_id != td.desserte_id
                    JOIN desserte dv ON dv.id = autre.desserte_id
                    JOIN station sv ON sv.id = dv.station_id
                    WHERE d.station_id = :stationId AND sv.latitude IS NOT NULL
                    LIMIT 1
                    SQL,
                ['stationId' => $station['id']],
            )->fetchAssociative();

            if (false === $voisin) {
                $io->warning("Station #{$station['id']} ({$station['label']}) : code_externe perime, mais aucun voisin positionne pour desambiguiser - ignoree.");
                continue;
            }

            $candidats = $this->connexion->executeQuery(
                'SELECT id, code_externe, ville, latitude, longitude FROM station WHERE label = :label AND code_externe IS NOT NULL AND latitude IS NOT NULL AND id != :id',
                ['label' => $station['label'], 'id' => $station['id']],
            )->fetchAllAssociative();

            $meilleur = null;
            $meilleureDistance = null;
            foreach ($candidats as $candidat) {
                if (!isset($zdcValides[$candidat['code_externe']])) {
                    continue;
                }
                $distance = $this->haversine((float) $voisin['latitude'], (float) $voisin['longitude'], (float) $candidat['latitude'], (float) $candidat['longitude']);
                if (null === $meilleureDistance || $distance < $meilleureDistance) {
                    $meilleureDistance = $distance;
                    $meilleur = $candidat;
                }
            }

            if (null === $meilleur || $meilleureDistance > self::DISTANCE_MAX_METRES) {
                $io->warning("Station #{$station['id']} ({$station['label']}) : aucun candidat homonyme a moins de ".self::DISTANCE_MAX_METRES.'m du voisin - ignoree.');
                continue;
            }

            $corrections[] = [
                'stationId' => (int) $station['id'],
                'label' => $station['label'],
                'zdcId' => (int) $meilleur['id'],
                'zdcCodeExterne' => $meilleur['code_externe'],
                'zdcVille' => $meilleur['ville'],
                'distance' => $meilleureDistance,
            ];
        }

        return $corrections;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $corrections = $this->trouverCorrections($io);
        $io->writeln(sprintf('%d correction(s) trouvee(s).', \count($corrections)));
        foreach ($corrections as $c) {
            $io->writeln(sprintf('  #%d %s <- #%d (%s, %d m)', $c['stationId'], $c['label'], $c['zdcId'], $c['zdcVille'] ?? '?', round($c['distance'])));
        }

        if ($dryRun) {
            $io->success('Dry-run, rien ecrit.');

            return Command::SUCCESS;
        }

        $nbCorrigees = 0;
        foreach ($corrections as $c) {
            $this->connexion->beginTransaction();
            try {
                $this->connexion->executeStatement(
                    <<<'SQL'
                        UPDATE station o
                        JOIN station z ON z.id = :zdcId
                        SET o.ville = COALESCE(o.ville, z.ville),
                            o.schema_x = COALESCE(o.schema_x, z.schema_x),
                            o.schema_y = COALESCE(o.schema_y, z.schema_y),
                            o.latitude = COALESCE(o.latitude, z.latitude),
                            o.longitude = COALESCE(o.longitude, z.longitude),
                            o.plan_id = COALESCE(o.plan_id, z.plan_id),
                            o.pole_echange_id = COALESCE(o.pole_echange_id, z.pole_echange_id),
                            o.accessibilite_pmr = COALESCE(o.accessibilite_pmr, z.accessibilite_pmr),
                            o.accessibilite_pmr_commentaire = COALESCE(o.accessibilite_pmr_commentaire, z.accessibilite_pmr_commentaire),
                            o.zone_tarifaire = COALESCE(o.zone_tarifaire, z.zone_tarifaire)
                        WHERE o.id = :stationId
                        SQL,
                    ['zdcId' => $c['zdcId'], 'stationId' => $c['stationId']],
                );

                foreach (self::TABLES_STATION_ID as $table) {
                    $this->connexion->executeStatement(
                        "UPDATE $table SET station_id = :stationId WHERE station_id = :zdcId",
                        ['stationId' => $c['stationId'], 'zdcId' => $c['zdcId']],
                    );
                }

                $this->connexion->executeStatement('DELETE FROM station WHERE id = :zdcId', ['zdcId' => $c['zdcId']]);

                // code_externe force (pas COALESCE) : la valeur existante est perimee, pas absente.
                $this->connexion->executeStatement(
                    'UPDATE station SET code_externe = :codeExterne WHERE id = :stationId',
                    ['codeExterne' => $c['zdcCodeExterne'], 'stationId' => $c['stationId']],
                );

                $this->connexion->commit();
                ++$nbCorrigees;
            } catch (\Throwable $e) {
                $this->connexion->rollBack();
                $io->error(sprintf('Echec sur %s (#%d/#%d) : %s', $c['label'], $c['stationId'], $c['zdcId'], $e->getMessage()));

                return Command::FAILURE;
            }
        }

        $io->success(sprintf('%d station(s) corrigee(s).', $nbCorrigees));

        return Command::SUCCESS;
    }
}
