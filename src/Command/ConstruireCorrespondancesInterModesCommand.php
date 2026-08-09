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
 * Cree les correspondances manquantes entre Metro/Tramway/RER a chaque station desservie par
 * plusieurs de ces modes (ex: Nation en Metro + RER, Val de Fontenay en RER + Metro...). Jusqu'ici
 * seules les correspondances Metro<->Metro avaient ete verifiees et saisies a la main (session du
 * 07-08/08/2026) : un changement de mode a une station (ex: Nation entree par le RER) ne trouvait
 * donc jamais de trajet, meme quand les deux lignes desservent physiquement la meme station (voir
 * TrajetFinder::construireGraphe, qui ne deduit JAMAIS une correspondance du simple partage d'une
 * Station — il faut une ligne Correspondance explicite).
 *
 * Volontairement limite a Metro/Tramway/RER (pas les bus) : le reseau complet a ~1400 lignes de bus,
 * generer une correspondance pour chaque paire a chaque arret partage creerait des dizaines de
 * milliers de lignes de faible valeur pour repondre au besoin reel (relier les modes lourds).
 *
 * Regroupement par LABEL de station, pas par id : de grandes gares (Gare du Nord, La Defense,
 * Saint-Denis, Cite Universitaire, Bondy...) existent en base comme plusieurs Station distinctes
 * pour le meme lieu reel (ZdCId IDFM different par mode/operateur dans le referentiel officiel) —
 * une premiere version groupant par station_id ratait systematiquement ces 22 gares, exactement
 * les gros hubs metro<->RER<->tram les plus utiles a relier. Fusionner ces Station en base serait
 * plus juste mais risque (voir l'incident de corruption documente dans
 * ImporterReseauCompletCommand) ; regrouper seulement ICI, sur les ~500 stations mode lourd (pas
 * les ~14000 nationales), est un perimetre assez restreint pour rester sur.
 *
 * La distance/temps de marche reste non renseignee (null) : TrajetFinder applique alors son
 * estimation par defaut (3 min, voir DUREE_CORRESPONDANCE_DEFAUT_MINUTES), comme deja indique dans
 * le disclaimer de la page /trajet. Rejouable : ignore les paires qui ont deja une Correspondance
 * (dans un sens ou l'autre), donc jamais de doublon avec les correspondances Metro<->Metro
 * existantes.
 */
#[AsCommand(name: 'app:construire-correspondances-inter-modes', description: 'Cree les correspondances manquantes entre Metro/Tramway/RER a chaque station partagee')]
class ConstruireCorrespondancesInterModesCommand extends Command
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

        // Un desserte_id par (station, ligne) desservie en mode lourd, regroupe par LABEL de
        // station (pas par id, voir docblock de la classe) — un label avec 2+ dessertes ici a
        // une correspondance potentielle a creer.
        $dessertesParStation = $connexion->executeQuery(
            <<<SQL
                SELECT s.label AS station_label, d.id AS desserte_id, d.ligne_id
                FROM desserte d
                INNER JOIN station s ON s.id = d.station_id
                INNER JOIN ligne l ON l.id = d.ligne_id
                INNER JOIN type_transport t ON t.id = l.type_transport_id
                WHERE t.label IN ($placeholders)
                ORDER BY s.label
                SQL,
            self::MODES_LOURDS,
        )->fetchAllAssociative();

        $parStation = [];
        foreach ($dessertesParStation as $row) {
            $parStation[$row['station_label']][] = ['desserte_id' => (int) $row['desserte_id'], 'ligne_id' => (int) $row['ligne_id']];
        }

        // Paires (desserteId_min, desserteId_max) deja couvertes par une Correspondance existante.
        $pairesExistantes = [];
        foreach ($connexion->executeQuery('SELECT desserte_a_id, desserte_b_id FROM correspondance')->fetchAllAssociative() as $row) {
            $a = (int) $row['desserte_a_id'];
            $b = (int) $row['desserte_b_id'];
            $pairesExistantes[min($a, $b).'|'.max($a, $b)] = true;
        }

        $desserteRepository = $this->entityManager->getRepository(Desserte::class);
        $nbCreees = 0;
        $nbStationsConcernees = 0;

        foreach ($parStation as $stationLabel => $dessertes) {
            $lignesDistinctes = array_unique(array_column($dessertes, 'ligne_id'));
            if (\count($lignesDistinctes) < 2) {
                continue; // un seul mode lourd a cette station, rien a relier
            }

            $nbStationsConcernees++;

            for ($i = 0, $n = \count($dessertes); $i < $n; ++$i) {
                for ($j = $i + 1; $j < $n; ++$j) {
                    if ($dessertes[$i]['ligne_id'] === $dessertes[$j]['ligne_id']) {
                        continue; // meme ligne (2 quais), pas une vraie correspondance
                    }

                    $idA = $dessertes[$i]['desserte_id'];
                    $idB = $dessertes[$j]['desserte_id'];
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
                }
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d correspondances creees sur %d stations desservies par plusieurs modes lourds.', $nbCreees, $nbStationsConcernees));

        return Command::SUCCESS;
    }
}
