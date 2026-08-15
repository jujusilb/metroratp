<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplit les champs de cheminement d'Acces (distance/temps de marche, marches, pente, largeur,
 * signaletique, sens) depuis pathways.txt (GTFS IDFM). Le fichier ne contient que des chemins
 * Acces -> quai (pathway_mode=1, "walkway" partout) : pas de chemin quai-a-quai exploitable pour
 * une correspondance inter-lignes (voir transfers.txt pour cette piste-la, pas encore exploitee).
 *
 * stair_count/max_slope/min_width/signposted_as/reversed_signposted_as sont vides sur les 4973
 * lignes du jeu de donnees actuel (verifie le 2026-08-15) : importes quand meme (colonnes toujours
 * lues, jamais ignorees) pour que la commande se remplisse d'elle-meme si IDFM les renseigne un
 * jour, conformement a l'ambition encyclopedique du site.
 *
 * Un Acces peut avoir plusieurs quais a des distances differentes (1127/2378 acces concernes,
 * ex: deux lignes a des profondeurs differentes) : on garde le chemin le plus court (par
 * `length`), et TOUTES ses colonnes (pas juste distance/temps) proviennent de ce meme chemin
 * choisi, pour rester coherentes entre elles.
 */
#[AsCommand(name: 'app:importer-temps-marche-acces', description: 'Importe le cheminement pietons reel Acces -> quai depuis pathways.txt')]
class ImporterTempsMarcheAccesCommand extends Command
{
    private const PATHWAYS_TXT = 'documentation/scripts/donnees-extraites/pathways.txt';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
        while (false !== ($ligne = fgetcsv($fichier))) {
            yield array_combine($header, $ligne);
        }
        fclose($fichier);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Lecture de pathways.txt (garde le chemin le plus court par Acces)...');
        $meilleurParAcces = [];
        foreach ($this->lireCsv(self::PATHWAYS_TXT) as $ligne) {
            if (!preg_match('/StopPlaceEntrance:(\d+)/', $ligne['from_stop_id'], $m)) {
                continue;
            }
            $accId = $m[1];
            $longueur = (float) $ligne['length'];

            if (isset($meilleurParAcces[$accId]) && $longueur >= $meilleurParAcces[$accId]['longueur']) {
                continue;
            }

            $meilleurParAcces[$accId] = [
                'longueur' => $longueur,
                'temps' => '' !== $ligne['traversal_time'] ? (int) $ligne['traversal_time'] : null,
                'marches' => '' !== $ligne['stair_count'] ? (int) $ligne['stair_count'] : null,
                'pente' => '' !== $ligne['max_slope'] ? (float) $ligne['max_slope'] : null,
                'largeur' => '' !== $ligne['min_width'] ? (float) $ligne['min_width'] : null,
                'signalisation' => '' !== $ligne['signposted_as'] ? $ligne['signposted_as'] : null,
                'signalisationInverse' => '' !== $ligne['reversed_signposted_as'] ? $ligne['reversed_signposted_as'] : null,
                'bidirectionnel' => '' !== $ligne['is_bidirectional'] ? (bool) (int) $ligne['is_bidirectional'] : null,
            ];
        }
        $io->info(sprintf('%d Acces avec au moins un cheminement connu.', \count($meilleurParAcces)));

        $io->section('Mise a jour des Acces...');
        $nbMaj = 0;
        foreach ($connexion->executeQuery('SELECT id, code_externe FROM acces WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $donnees = $meilleurParAcces[$row['code_externe']] ?? null;
            if (null === $donnees) {
                continue;
            }

            $connexion->executeStatement(
                'UPDATE acces SET distance_marche_metres = ?, temps_marche_secondes = ?, nombre_marches = ?,
                    pente_max_pourcent = ?, largeur_min_metres = ?, signalisation = ?, signalisation_inverse = ?,
                    cheminement_bidirectionnel = ?
                 WHERE id = ?',
                [
                    $donnees['longueur'],
                    $donnees['temps'],
                    $donnees['marches'],
                    $donnees['pente'],
                    $donnees['largeur'],
                    $donnees['signalisation'],
                    $donnees['signalisationInverse'],
                    null === $donnees['bidirectionnel'] ? null : (int) $donnees['bidirectionnel'],
                    $row['id'],
                ],
            );
            ++$nbMaj;
        }

        $io->success(sprintf('%d Acces mis a jour avec leur cheminement.', $nbMaj));

        return Command::SUCCESS;
    }
}
