<?php

namespace App\Command;

use App\Entity\PoleEchange;
use App\Repository\PoleEchangeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les PoleEchange IDFM depuis poles-d-echange.csv (10 poles seulement), puis assigne
 * Station::poleEchange via une correspondance nominative VERIFIEE A LA MAIN (pas de matching flou
 * automatique).
 *
 * Le dataset source ne contient qu'un id et un nom de pole, sans cle de rattachement vers les
 * Station (pas de ZdCId) : un simple "LIKE %nom%" est dangereux (verifie en explorant les
 * donnees reelles - ex: "Roissy" ou "Charles de Gaulle" seuls matchent des dizaines d'arrets de
 * bus/gares sans rapport partout en Ile-de-France, "Paris Nord"/"Paris Est" ne matchent aucune
 * Station reelle a ce nom). La liste ci-dessous a ete construite en interrogeant manuellement
 * chaque candidat (label exact + ville attendue, pour distinguer une Station homonyme sans
 * rapport - ex: "Châtelet" existe aussi a Montereau-Fault-Yonne, "Saint-Michel" a Étampes et
 * Moissy-Cramayel) : voir documentation/commande.md pour le detail de cette verification.
 *
 * ville=null cible specifiquement la Station "originale" homonyme (voir TODO.md, doublons
 * Station) : ces Station sans ville renseignee sont celles reellement utilisees par les donnees
 * de Desserte/Troncon existantes.
 */
#[AsCommand(name: 'app:importer-poles-echange', description: 'Importe les PoleEchange IDFM et assigne Station::poleEchange via une liste verifiee a la main')]
class ImporterPolesEchangeCommand extends Command
{
    private const POLES_CSV = 'documentation/scripts/donnees-extraites/poles-d-echange.csv';

    /**
     * PdEId => liste de [label exact, ville attendue ou null].
     *
     * @var array<string, list<array{0: string, 1: ?string}>>
     */
    private const STATIONS_PAR_POLE = [
        '415730' => [ // Gare Montparnasse
            ['Gare Montparnasse', 'Paris 15e'],
            ['Montparnasse — Bienvenüe', null],
        ],
        '474262' => [ // Paris Saint-Lazare - Opéra
            ['Saint-Lazare', null],
            ['Gare Saint-Lazare', 'Paris 8e'],
            ['Haussmann Saint-Lazare', null],
            ['Opéra', null],
            ['Opéra', 'Paris 9e'],
        ],
        '415731' => [ // Aéroport d'Orly
            ["Aéroport d'Orly", null],
            ['Orly Ville', null],
            ['Orly Ville', 'Orly'],
        ],
        '474259' => [ // Paris Est
            ["Gare de l'Est", null],
            ["Gare de l'Est", 'Paris 10e'],
        ],
        '474265' => [ // Tour Eiffel
            ['Tour Eiffel', 'Paris 7e'],
            ['Champ de Mars Tour Eiffel', null],
            ['Champ de Mars Tour Eiffel', 'Paris 15e'],
        ],
        '474266' => [ // La Muette - Boulainvilliers
            ['La Muette', null],
            ['La Muette', 'Paris 16e'],
            ['Boulainvilliers', null],
            ['Boulainvilliers', 'Paris 16e'],
        ],
        '474263' => [ // Châtelet - Les Halles
            ['Châtelet', null],
            ['Châtelet', 'Paris 4e'],
            ['Les Halles', null],
            ['Châtelet - Les Halles', null],
        ],
        '474260' => [ // Paris Nord
            ['Gare du Nord', null],
            ['Gare du Nord', 'Paris 10e'],
        ],
        '415732' => [ // Aéroport Roissy Charles de Gaulle
            ['Aéroport Charles de Gaulle 2 (Terminal 2)', null],
            ['Aéroport CDG 1 (Terminal 3) - RER', null],
        ],
        '474264' => [ // Saint-Michel - La Sorbonne
            ['Saint-Michel', null],
            ['Saint-Michel Notre-Dame', null],
            ['Saint-Michel Notre-Dame', 'Paris 5e'],
            ['Cluny — La Sorbonne', null],
            ['Cluny - La Sorbonne', 'Paris 5e'],
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PoleEchangeRepository $poleEchangeRepository,
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
        $connexion = $this->entityManager->getConnection();

        $io->section('Import des PoleEchange...');
        $nbCrees = 0;
        $nbMaj = 0;
        foreach ($this->lireCsv(self::POLES_CSV) as $ligne) {
            $codeExterne = $ligne['PdEId'];
            $pole = $this->poleEchangeRepository->trouverParCodeExterne($codeExterne) ?? new PoleEchange();
            $estNouveau = null === $pole->getId();

            $pole->setCodeExterne($codeExterne);
            $pole->setLabel($ligne['PdEName']);

            if ($estNouveau) {
                $this->entityManager->persist($pole);
                ++$nbCrees;
            } else {
                ++$nbMaj;
            }
        }
        $this->entityManager->flush();
        $io->info(sprintf('%d PoleEchange crees, %d mis a jour.', $nbCrees, $nbMaj));

        $poleIdParCode = [];
        foreach ($connexion->executeQuery('SELECT code_externe, id FROM pole_echange')->iterateAssociative() as $row) {
            $poleIdParCode[$row['code_externe']] = (int) $row['id'];
        }

        $io->section('Assignation de Station::poleEchange (liste verifiee a la main)...');
        $nbAssignes = 0;
        $nbIntrouvables = 0;
        foreach (self::STATIONS_PAR_POLE as $codeExterne => $candidats) {
            $poleId = $poleIdParCode[$codeExterne] ?? null;
            if (null === $poleId) {
                continue;
            }

            foreach ($candidats as [$label, $ville]) {
                $sql = 'SELECT id FROM station WHERE label = ? AND '.(null === $ville ? 'ville IS NULL' : 'ville = ?');
                $params = null === $ville ? [$label] : [$label, $ville];
                $stationId = $connexion->executeQuery($sql, $params)->fetchOne();

                if (false === $stationId) {
                    ++$nbIntrouvables;
                    $io->warning(sprintf('Aucune Station trouvee pour "%s" (ville=%s).', $label, $ville ?? 'NULL'));
                    continue;
                }

                $connexion->executeStatement('UPDATE station SET pole_echange_id = ? WHERE id = ?', [$poleId, $stationId]);
                ++$nbAssignes;
            }
        }

        $io->success(sprintf('%d Stations assignees a leur PoleEchange (%d candidats introuvables).', $nbAssignes, $nbIntrouvables));

        return Command::SUCCESS;
    }
}
