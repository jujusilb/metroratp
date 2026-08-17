<?php

namespace App\Command;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bouche un trou concret dans TrajetFinder, signale par l'utilisateur (2026-08-17) : un trajet
 * qui commence en bus ne proposait jamais de changer pour le metro/RER/tram, meme quand un
 * changement existe reellement sur le terrain. Cause racine (voir "Stations dupliquees",
 * documentation/TODO.md) : la Station "historique" (sans codeExterne, celle qui porte la vraie
 * Desserte metro/RER/tram et sa topologie de Troncon) et sa jumelle "GTFS" (avec codeExterne,
 * celle sur laquelle sont rattachees TOUTES les Correspondance construites depuis transfers.txt/
 * ZdC — bus<->bus, bus<->metro, etc.) sont deux Station DISTINCTES pour le meme lieu reel :
 * verifie sur Le Kremlin-Bicêtre, ou la desserte metro (ligne 7) vit sur la Station historique
 * (aucune Correspondance) tandis que ses 6 dessertes de bus vivent sur la jumelle GTFS (aucune
 * Correspondance vers le metro non plus) — TrajetFinder ne peut alors JAMAIS relier les deux,
 * quel que soit le sens du trajet.
 *
 * ConstruireCorrespondancesInterModesCommand cree deja des Correspondance entre modes LOURDS
 * (metro/RER/tram) partageant un label, mais jamais vers le bus (volontairement, voir son
 * docblock) : cette commande-ci comble specifiquement ce manque, pour les Station historiques a
 * desserte lourde, en creant une Correspondance entre CHAQUE Desserte de la Station historique et
 * CHAQUE Desserte de sa jumelle - restaure la connectivite complete (bus compris) sans fusionner
 * les Station elles-memes (operation bien plus risquee, voir TODO.md, volontairement pas faite).
 *
 * Ne cible QUE les Station historiques dont le label correspond a EXACTEMENT une seule autre
 * Station porteuse d'un codeExterne (meme discipline que partout ailleurs cette session pour ce
 * genre de rapprochement par nom : des labels generiques comme "République"/"Gambetta" existent
 * dans des dizaines de communes sans rapport, un rapprochement ambigu serait incorrect). Ignore
 * aussi les paires de dessertes qui ont deja une Correspondance existante (rejouable).
 */
#[AsCommand(name: 'app:construire-correspondances-stations-dupliquees', description: 'Relie chaque Station historique (metro/RER/tram) a sa jumelle GTFS quand elle est identifiable sans ambiguite')]
class ConstruireCorrespondancesStationsDupliqueesCommand extends Command
{
    private const MODES_LOURDS = ['Métro', 'Tramway', 'RER'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $placeholders = implode(',', array_fill(0, \count(self::MODES_LOURDS), '?'));

        $io->section('Recherche des Station historiques (sans codeExterne) a desserte lourde...');
        $historiques = $connexion->executeQuery(
            <<<SQL
                SELECT DISTINCT s.id, s.label
                FROM station s
                JOIN desserte d ON d.station_id = s.id
                JOIN ligne l ON l.id = d.ligne_id
                JOIN type_transport tt ON tt.id = l.type_transport_id
                WHERE s.code_externe IS NULL AND tt.label IN ($placeholders)
                SQL,
            self::MODES_LOURDS,
        )->fetchAllAssociative();
        $io->info(\count($historiques).' Station historiques a desserte lourde trouvees.');

        $pairesExistantes = [];
        foreach ($connexion->executeQuery('SELECT desserte_a_id, desserte_b_id FROM correspondance')->fetchAllAssociative() as $row) {
            $a = (int) $row['desserte_a_id'];
            $b = (int) $row['desserte_b_id'];
            $pairesExistantes[min($a, $b).'|'.max($a, $b)] = true;
        }

        $desserteRepository = $this->entityManager->getRepository(Desserte::class);
        $nbCreees = 0;
        $nbStationsPontees = 0;
        $nbAmbigues = 0;
        $nbSansJumeau = 0;

        foreach ($historiques as $historique) {
            $jumeaux = $connexion->executeQuery(
                'SELECT id FROM station WHERE label = ? AND code_externe IS NOT NULL',
                [$historique['label']],
            )->fetchFirstColumn();

            if (1 !== \count($jumeaux)) {
                \count($jumeaux) > 1 ? ++$nbAmbigues : ++$nbSansJumeau;
                continue;
            }
            $jumelleId = (int) $jumeaux[0];

            $dessertesHistorique = $connexion->executeQuery('SELECT id FROM desserte WHERE station_id = ?', [$historique['id']])->fetchFirstColumn();
            $dessertesJumelle = $connexion->executeQuery('SELECT id FROM desserte WHERE station_id = ?', [$jumelleId])->fetchFirstColumn();
            if ([] === $dessertesHistorique || [] === $dessertesJumelle) {
                continue;
            }

            $creeesPourCetteStation = 0;
            foreach ($dessertesHistorique as $idA) {
                foreach ($dessertesJumelle as $idB) {
                    $idA = (int) $idA;
                    $idB = (int) $idB;
                    $cle = min($idA, $idB).'|'.max($idA, $idB);
                    if (isset($pairesExistantes[$cle])) {
                        continue;
                    }

                    $desserteA = $desserteRepository->find($idA);
                    $desserteB = $desserteRepository->find($idB);
                    if (null === $desserteA || null === $desserteB) {
                        continue;
                    }

                    $correspondance = new Correspondance();
                    $correspondance->setDesserteA($desserteA);
                    $correspondance->setDesserteB($desserteB);
                    $correspondance->setInZone(true);
                    $this->entityManager->persist($correspondance);

                    $pairesExistantes[$cle] = true;
                    ++$nbCreees;
                    ++$creeesPourCetteStation;
                }
            }

            if ($creeesPourCetteStation > 0) {
                ++$nbStationsPontees;
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $io->success(sprintf(
            '%d correspondances creees, pontant %d Station historiques vers leur jumelle GTFS (%d labels ambigus ignores, %d sans jumelle trouvee).',
            $nbCreees,
            $nbStationsPontees,
            $nbAmbigues,
            $nbSansJumeau,
        ));

        return Command::SUCCESS;
    }
}
