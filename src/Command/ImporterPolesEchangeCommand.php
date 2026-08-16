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
 * Station::poleEchange en priorite via le referentiel officiel relations.csv (colonne ZdCId, qui
 * correspond exactement a Station::codeExterne).
 *
 * Ancienne methode (avant le depouillement de relations.csv, conservee ici pour l'historique - voir
 * documentation/commande.md) : le dataset poles-d-echange.csv ne contient qu'un id et un nom de
 * pole, sans cle de rattachement vers les Station. Un simple "LIKE %nom%" etait dangereux (verifie
 * en explorant les donnees reelles - ex: "Roissy" ou "Charles de Gaulle" seuls matchent des
 * dizaines d'arrets de bus/gares sans rapport partout en Ile-de-France). Le rattachement se
 * faisait donc via une liste de correspondances nominatives (label exact + ville attendue)
 * verifiees a la main, une par une.
 *
 * relations.csv (le referentiel PdE/ZdC/ZdA/ArR/ArT complet, deja utilise pour Acces via
 * pathways.txt) contient en realite une colonne ZdCId pour chaque ligne qui a un PdEId non-nul :
 * verifie que les 34 ZdCId distincts trouves correspondent EXACTEMENT (100%, 34/34) a un
 * Station::codeExterne existant, et que les 10 PdEId distincts correspondent EXACTEMENT aux 10
 * PoleEchange::codeExterne deja importes. Le matching est donc desormais une jointure exacte sur
 * cle officielle, plus fiable que l'ancienne liste manuelle.
 *
 * PIEGE DECOUVERT en remplacant l'ancienne liste : le reseau metro contient des Station "doublons"
 * (voir TODO.md, "Stations dupliquees") - une meme gare physique existe parfois sous deux lignes
 * distinctes en base : une Station "historique" (sans code_externe, portant les vraies Desserte du
 * reseau metro/RER/tram) ET une Station "GTFS" plus recente (avec code_externe, importee via le
 * referentiel officiel, souvent avec une ville renseignee). relations.csv, etant sourcee du GTFS,
 * ne peut par construction adresser QUE les Station avec code_externe - 16 Station historiques
 * (ex: id 88 "Montparnasse — Bienvenüe", 4 vraies Desserte) resteraient donc silencieusement sans
 * PoleEchange si on se fiait uniquement a relations.csv, alors qu'elles portent les donnees
 * reellement utilisees ailleurs dans l'appli. LEGACY_GAP_SANS_CODE_EXTERNE ci-dessous est le sous-
 * ensemble minimal de l'ancienne liste manuelle (verifie label + ville IS NULL + code_externe IS
 * NULL) qui comble precisement ce trou structurel, en plus du matching officiel - jamais a la place.
 */
#[AsCommand(name: 'app:importer-poles-echange', description: 'Importe les PoleEchange IDFM et assigne Station::poleEchange via relations.csv (ZdCId) + complement pour les Station historiques sans code_externe')]
class ImporterPolesEchangeCommand extends Command
{
    private const POLES_CSV = 'documentation/scripts/donnees-extraites/poles-d-echange.csv';
    private const RELATIONS_CSV = 'documentation/scripts/donnees-extraites/relations.csv';

    /**
     * PdEId => liste de labels de Station "historiques" (ville IS NULL, code_externe IS NULL)
     * introuvables via relations.csv car depourvues de code_externe. Verifie une a une (voir
     * documentation/commande.md) : chaque label a ete confirme comme correspondant a exactement une
     * Station reelle, porteuse de vraies Desserte, appartenant bien au pole indique.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY_GAP_SANS_CODE_EXTERNE = [
        '415730' => ['Montparnasse — Bienvenüe'],
        '474262' => ['Saint-Lazare', 'Opéra'],
        '415731' => ["Aéroport d'Orly", 'Orly Ville'],
        '474259' => ["Gare de l'Est"],
        '474265' => ['Champ de Mars Tour Eiffel'],
        '474266' => ['La Muette', 'Boulainvilliers'],
        '474263' => ['Châtelet'],
        '474260' => ['Gare du Nord'],
        '415732' => ['Aéroport Charles de Gaulle 2 (Terminal 2)', 'Aéroport CDG 1 (Terminal 3) - RER'],
        '474264' => ['Saint-Michel', 'Saint-Michel Notre-Dame', 'Cluny — La Sorbonne'],
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

        $io->section('Lecture de relations.csv (ZdCId => PdEId)...');
        $pdeIdParZdcId = [];
        foreach ($this->lireCsv(self::RELATIONS_CSV) as $ligne) {
            if ('' === $ligne['PdEId'] || '' === $ligne['ZdCId']) {
                continue;
            }
            $pdeIdParZdcId[$ligne['ZdCId']] = $ligne['PdEId'];
        }
        $io->info(sprintf('%d couples ZdCId => PdEId distincts trouves.', count($pdeIdParZdcId)));

        $io->section('Assignation de Station::poleEchange (via relations.csv, cle officielle ZdCId = Station::codeExterne)...');
        // Reinitialise d'abord toute assignation existante : cette commande est desormais la seule
        // source de verite pour Station::poleEchange (remplace l'ancienne liste manuelle), donc un
        // rejeu doit repartir de zero pour rester idempotent et ne pas laisser d'anciennes
        // assignations orphelines sur des Station dupliquees non retenues par relations.csv.
        $connexion->executeStatement('UPDATE station SET pole_echange_id = NULL');

        $nbAssignes = 0;
        $nbIntrouvables = 0;
        foreach ($pdeIdParZdcId as $zdcId => $pdeId) {
            $poleId = $poleIdParCode[$pdeId] ?? null;
            if (null === $poleId) {
                continue;
            }

            $stationId = $connexion->executeQuery('SELECT id FROM station WHERE code_externe = ?', [$zdcId])->fetchOne();
            if (false === $stationId) {
                ++$nbIntrouvables;
                $io->warning(sprintf('Aucune Station trouvee pour code_externe="%s" (ZdCId).', $zdcId));
                continue;
            }

            $connexion->executeStatement('UPDATE station SET pole_echange_id = ? WHERE id = ?', [$poleId, $stationId]);
            ++$nbAssignes;
        }

        $io->success(sprintf('%d Stations assignees a leur PoleEchange via relations.csv (%d ZdCId introuvables).', $nbAssignes, $nbIntrouvables));

        $io->section('Complement pour les Station historiques sans code_externe (LEGACY_GAP_SANS_CODE_EXTERNE)...');
        $nbLegacy = 0;
        foreach (self::LEGACY_GAP_SANS_CODE_EXTERNE as $codeExterne => $labels) {
            $poleId = $poleIdParCode[$codeExterne] ?? null;
            if (null === $poleId) {
                continue;
            }

            foreach ($labels as $label) {
                $stationId = $connexion->executeQuery(
                    'SELECT id FROM station WHERE label = ? AND ville IS NULL AND code_externe IS NULL',
                    [$label]
                )->fetchOne();

                if (false === $stationId) {
                    $io->warning(sprintf('Legacy: aucune Station trouvee pour "%s" (ville IS NULL, code_externe IS NULL).', $label));
                    continue;
                }

                $connexion->executeStatement('UPDATE station SET pole_echange_id = ? WHERE id = ?', [$poleId, $stationId]);
                ++$nbLegacy;
            }
        }
        $io->success(sprintf('%d Stations historiques completees en plus.', $nbLegacy));

        return Command::SUCCESS;
    }
}
