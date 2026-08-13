<?php

namespace App\Command;

use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les coordonnees des stations sur le plan schematique officiel du reseau, depuis le
 * jeu de donnees "Gares et stations du reseau ferre schematique d'Ile-de-France (grand format)"
 * publie par Ile-de-France Mobilites (data.iledefrance-mobilites.fr, dataset schema_gares-gf).
 * Ce ne sont PAS des coordonnees geographiques : ce plan deforme volontairement l'espace pour
 * rester lisible (comme le plan RATP officiel), ce qui est justement ce qu'on veut pour
 * visualiser un trajet sans que les stations du centre-ville se chevauchent.
 *
 * Le nom de station est la seule cle de rapprochement disponible avec ce jeu de donnees externe :
 * on normalise les deux cotes (accents, tirets, casse) et on applique un petit dictionnaire pour
 * les variantes de nommage restantes, plus une correspondance par inclusion de mots entiers en
 * dernier recours (jamais de correspondance approximative type distance de Levenshtein : testee
 * empiriquement, elle a mal apparie deux vraies stations differentes qui partagent un gros
 * suffixe commun - "Mairie d'Aubervilliers" avec "Fort d'Aubervilliers").
 *
 * Couvre tous les modes presents dans la source (metro, RER, tram, train/Transilien) - pas
 * seulement le metro comme la premiere version de cette commande : le mode NAVETTE (10 lignes,
 * navettes automatiques type CDGVAL/Orlyval) est ignore faute d'equivalent dans notre
 * TypeTransport. Format de colonnes mis a jour (le precedent export utilisait des entetes en
 * minuscules 'nom_gare'/'mode'/'x'/'y' ; l'export courant utilise 'NOM_GARE'/'MODE_'/'X'/'Y').
 */
#[AsCommand(name: 'app:importer-coordonnees-schema', description: 'Importe les coordonnees du plan schematique IDFM pour les stations (tous modes)')]
class ImporterCoordonneesSchemaCommand extends Command
{
    private const CORRESPONDANCES_MANUELLES = [
        'bibliotheque francois mitterrand' => 'bibliotheque francois mitterand',
        'javel parc andre citroen' => 'javel',
        'la defense' => 'la defense grande arche',
        'le peletier' => 'le pelletier',
        'les courtilles' => 'asnieres genevilliers les courtilles',
        'maisons alfort les juilliottes' => 'maisons alfort les julliottes',
        'montparnasse bienvenue' => 'montparnasse',
        'pereire' => 'pereire levallois',
        'pointe du lac' => 'creteil pointe du lac',
        'poissonniere' => 'poissoniere',
        'porte de clignancourt' => 'porte de clignacourt',
        'rue saint maur' => 'saint maur',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du CSV schema_gares-gf (export data.iledefrance-mobilites.fr, separateur ;)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'N\'ecrit rien en base, affiche seulement le rapport')
        ;
    }

    private function normaliser(string $label): string
    {
        $label = str_replace(['—', '–', '-'], ' ', $label);
        $label = \Normalizer::normalize($label, \Normalizer::FORM_D);
        $label = preg_replace('/\p{Mn}/u', '', $label);
        $label = strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label);
        $label = trim($label);

        return self::CORRESPONDANCES_MANUELLES[$label] ?? $label;
    }

    /**
     * @param array<string, array{x: float, y: float}> $coordsParNomNormalise
     * @return array{x: float, y: float}|null
     */
    private function trouverCorrespondance(string $normalise, array $coordsParNomNormalise): ?array
    {
        if (isset($coordsParNomNormalise[$normalise])) {
            return $coordsParNomNormalise[$normalise];
        }

        $motsNormalise = array_filter(explode(' ', $normalise), static fn ($m) => strlen($m) >= 3);
        foreach ($coordsParNomNormalise as $candidatNormalise => $coords) {
            $motsCandidat = array_filter(explode(' ', $candidatNormalise), static fn ($m) => strlen($m) >= 3);
            if (0 === count($motsNormalise) || 0 === count($motsCandidat)) {
                continue;
            }
            $plusCourt = count($motsNormalise) <= count($motsCandidat) ? $motsNormalise : $motsCandidat;
            $plusLong = count($motsNormalise) <= count($motsCandidat) ? $motsCandidat : $motsNormalise;
            if (0 === count(array_diff($plusCourt, $plusLong))) {
                return $coords;
            }
        }

        return null;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $fh = fopen($input->getArgument('fichier'), 'r');
        if (false === $fh) {
            $io->error('Fichier introuvable : ' . $input->getArgument('fichier'));

            return Command::FAILURE;
        }

        $header = fgetcsv($fh, 0, ';');
        $idxNom = array_search('NOM_GARE', $header, true);
        $idxMode = array_search('MODE_', $header, true);
        $idxX = array_search('X', $header, true);
        $idxY = array_search('Y', $header, true);

        // Modes couverts par ce dataset et ayant un TypeTransport correspondant chez nous - voir
        // docblock de la classe (NAVETTE ignore faute d'equivalent).
        $modesConnus = ['METRO' => true, 'RER' => true, 'TRAM' => true, 'Tramway' => true, 'TRAIN' => true];

        $parNom = [];
        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            if (!isset($modesConnus[$row[$idxMode] ?? ''])) {
                continue;
            }
            $x = (float) $row[$idxX];
            $y = (float) $row[$idxY];
            if (0.0 === $x && 0.0 === $y) {
                // Coordonnees manquantes dans la source pour cette station : (0,0) n'est pas
                // une position schematique plausible.
                continue;
            }
            $parNom[$row[$idxNom]][] = ['x' => $x, 'y' => $y];
        }
        fclose($fh);

        $coordsParNomNormalise = [];
        foreach ($parNom as $nom => $points) {
            $coordsParNomNormalise[$this->normaliser($nom)] = [
                'x' => array_sum(array_column($points, 'x')) / count($points),
                'y' => array_sum(array_column($points, 'y')) / count($points),
            ];
        }
        $io->writeln(sprintf('Stations dans la source (tous modes couverts) : %d', count($coordsParNomNormalise)));

        // Ne candidate que les Stations desservies par un mode ferre lourd (celui-meme couvert par
        // cette source : metro/RER/tram/train) : sans ce garde-fou, la correspondance par nom (avec
        // repli par inclusion de mots) matchait aussi des milliers d'arrets de BUS au nom proche
        // d'une gare/station (ex: un arret de bus "Nation" quelconque hors de Paris) - 4863
        // "correspondances" trouvees sur l'ensemble des ~14000 Stations, bien trop pour les ~1000
        // lieux reels couverts par la source. Ce dataset ne concerne jamais le bus, donc une
        // Station uniquement desservie par bus ne peut jamais etre une bonne correspondance.
        $stationsCandidates = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Station::class, 's')
            ->where(
                'EXISTS (SELECT 1 FROM App\Entity\Desserte d JOIN d.ligne l JOIN l.typeTransport tt
                    WHERE d.station = s AND tt.label IN (:modesLourds))'
            )
            ->setParameter('modesLourds', ['Métro', 'RER', 'Tramway', 'Train'])
            ->getQuery()
            ->getResult()
        ;
        $io->writeln(sprintf('Stations candidates (desservies par metro/RER/tram/train) : %d', count($stationsCandidates)));

        $matches = 0;
        $nonTrouvees = [];

        foreach ($stationsCandidates as $station) {
            $trouve = $this->trouverCorrespondance($this->normaliser($station->getLabel()), $coordsParNomNormalise);
            if (null === $trouve) {
                $nonTrouvees[] = $station->getLabel();
                continue;
            }

            $matches++;
            if (!$dryRun) {
                $station->setSchemaX($trouve['x']);
                $station->setSchemaY($trouve['y']);
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('%d stations positionnees (%s)', $matches, $dryRun ? 'dry-run, rien ecrit' : 'ecrit en base'));

        if (count($nonTrouvees) > 0) {
            $io->section(sprintf('%d stations sans coordonnees trouvees (garderont schemaX/Y null) :', count($nonTrouvees)));
            sort($nonTrouvees);
            foreach ($nonTrouvees as $label) {
                $io->writeln("  - $label");
            }
        }

        return Command::SUCCESS;
    }
}
