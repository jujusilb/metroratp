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
 * Fusionne les paires de Station dupliquees documentees depuis le 2026-08-09 (TODO.md, section
 * "Stations Metro/Tramway/RER dupliquees") : une Station "originale" (creee a la main tot dans le
 * projet, code_externe NULL, reellement liee/visitee - voir StationRepository::trouverIdCanoniqueParZdc())
 * et sa jumelle "ZdC-liee" (code_externe rempli par app:importer-reseau-complet), meme lieu reel.
 *
 * Perimetre volontairement restreint aux paires NON AMBIGUES : meme label exact ET coordonnees a
 * moins de 300m (verifie sur l'etat actuel de la base : 371 des 534 Station "originale" ont
 * exactement 1 candidat dans ce rayon, jamais 0 ni plusieurs - les ~163 restantes, sans label
 * correspondant ou sans coordonnees pour verifier, restent volontairement non fusionnees plutot
 * que devinees). Le simple rapprochement par label seul (sans le filtre de distance) est
 * dangereux : 83 Station "originale" ont plusieurs homonymes par label (ex: "Victor Hugo", 35
 * candidats - un nom de rue tres commun, sans rapport avec la meme station physique).
 *
 * Pour chaque paire retenue, dans une transaction :
 *  - l'originale recoit les colonnes de la jumelle qui lui manquent (COALESCE : code_externe,
 *    ville, schema_x/y, plan_id, pole_echange_id, accessibilite_pmr(_commentaire), zone_tarifaire -
 *    verifie sans aucun conflit de valeur sur les 371 paires, uniquement des trous a combler) ;
 *  - les 8 tables qui referencent Station par FK directe (desserte, sortie, equipement_arret,
 *    position_rame, defibrillateur, fontaine_eau, point_de_vente, sanisette_publique, sanitaire)
 *    sont repointees vers l'originale (verifie : aucune des 371 paires n'a de Desserte sur la
 *    MEME Ligne des deux cotes, donc aucun risque de doublon (station,ligne) apres fusion -
 *    Correspondance/Direction/TronconDesserte n'ont pas besoin d'etre touchees, elles referencent
 *    Desserte dont l'id ne change pas) ;
 *  - la Station ZdC-liee, desormais orpheline, est supprimee.
 *
 * Idempotente : recalcule les paires a chaque execution (jamais de liste figee) - une fois
 * fusionnee, une paire disparait naturellement du perimetre (code_externe n'est plus NULL cote
 * originale).
 */
#[AsCommand(name: 'app:fusionner-stations-dupliquees', description: 'Fusionne les paires de Station dupliquees non ambigues (meme label + coordonnees ~identiques)')]
class FusionnerStationsDupliqueesCommand extends Command
{
    private const SEUIL_METRES = 300.0;

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
     * @return array<int, array{originaleId: int, zdcId: int, label: string}>
     */
    private function trouverPaires(): array
    {
        $originales = $this->connexion->executeQuery(
            'SELECT id, label, latitude, longitude FROM station WHERE code_externe IS NULL AND latitude IS NOT NULL'
        )->fetchAllAssociative();

        $zdcParLabel = [];
        foreach ($this->connexion->executeQuery(
            'SELECT id, label, latitude, longitude, code_externe FROM station WHERE code_externe IS NOT NULL AND latitude IS NOT NULL'
        )->iterateAssociative() as $row) {
            $zdcParLabel[$row['label']][] = $row;
        }

        $paires = [];
        foreach ($originales as $o) {
            $proches = [];
            foreach ($zdcParLabel[$o['label']] ?? [] as $z) {
                if ($this->haversine((float) $o['latitude'], (float) $o['longitude'], (float) $z['latitude'], (float) $z['longitude']) <= self::SEUIL_METRES) {
                    $proches[] = $z;
                }
            }
            if (1 === \count($proches)) {
                $paires[] = [
                    'originaleId' => (int) $o['id'],
                    'zdcId' => (int) $proches[0]['id'],
                    'zdcCodeExterne' => $proches[0]['code_externe'],
                    'label' => $o['label'],
                ];
            }
        }

        return $paires;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $paires = $this->trouverPaires();
        $io->writeln(sprintf('%d paire(s) non ambigue(s) trouvee(s).', \count($paires)));

        if ($dryRun) {
            foreach ($paires as $p) {
                $io->writeln(sprintf('  #%d (originale) <- #%d (ZdC) : %s', $p['originaleId'], $p['zdcId'], $p['label']));
            }
            $io->success('Dry-run, rien ecrit.');

            return Command::SUCCESS;
        }

        $nbFusionnees = 0;
        foreach ($paires as $p) {
            $this->connexion->beginTransaction();
            try {
                // code_externe est volontairement laisse de cote ici : tant que la ligne ZdC
                // existe encore, copier sa valeur sur l'originale violerait la contrainte
                // d'unicite (les deux lignes porteraient alors la meme valeur simultanement,
                // meme au sein de la meme transaction - MySQL ne differe pas cette verification).
                $this->connexion->executeStatement(
                    <<<'SQL'
                        UPDATE station o
                        JOIN station z ON z.id = :zdcId
                        SET o.ville = COALESCE(o.ville, z.ville),
                            o.schema_x = COALESCE(o.schema_x, z.schema_x),
                            o.schema_y = COALESCE(o.schema_y, z.schema_y),
                            o.plan_id = COALESCE(o.plan_id, z.plan_id),
                            o.pole_echange_id = COALESCE(o.pole_echange_id, z.pole_echange_id),
                            o.accessibilite_pmr = COALESCE(o.accessibilite_pmr, z.accessibilite_pmr),
                            o.accessibilite_pmr_commentaire = COALESCE(o.accessibilite_pmr_commentaire, z.accessibilite_pmr_commentaire),
                            o.zone_tarifaire = COALESCE(o.zone_tarifaire, z.zone_tarifaire)
                        WHERE o.id = :originaleId
                        SQL,
                    ['zdcId' => $p['zdcId'], 'originaleId' => $p['originaleId']],
                );

                foreach (self::TABLES_STATION_ID as $table) {
                    $this->connexion->executeStatement(
                        "UPDATE $table SET station_id = :originaleId WHERE station_id = :zdcId",
                        ['originaleId' => $p['originaleId'], 'zdcId' => $p['zdcId']],
                    );
                }

                $this->connexion->executeStatement('DELETE FROM station WHERE id = :zdcId', ['zdcId' => $p['zdcId']]);

                // Ligne ZdC supprimee : la valeur est maintenant libre, ecrite en dernier.
                $this->connexion->executeStatement(
                    'UPDATE station SET code_externe = :codeExterne WHERE id = :originaleId',
                    ['codeExterne' => $p['zdcCodeExterne'], 'originaleId' => $p['originaleId']],
                );

                $this->connexion->commit();
                ++$nbFusionnees;
            } catch (\Throwable $e) {
                $this->connexion->rollBack();
                $io->error(sprintf('Echec sur %s (#%d/#%d) : %s', $p['label'], $p['originaleId'], $p['zdcId'], $e->getMessage()));

                return Command::FAILURE;
            }
        }

        $io->success(sprintf('%d paire(s) fusionnee(s).', $nbFusionnees));

        return Command::SUCCESS;
    }
}
