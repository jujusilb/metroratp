<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Gestionnaire;
use App\Entity\Ligne;
use App\Entity\Materiel;
use App\Entity\MaterielLigne;
use App\Entity\Station;
use App\Entity\TypeMateriel;
use App\Entity\TypeTransport;
use App\Repository\GestionnaireRepository;
use App\Repository\LigneRepository;
use App\Repository\MaterielRepository;
use App\Repository\StationRepository;
use App\Repository\TypeMaterielRepository;
use App\Repository\TypeTransportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cree les 5 lignes RER (A-E) et toutes leurs stations/dessertes reelles, a partir d'un CSV
 * (colonnes : ligne,station) genere depuis le GTFS complet IDFM (trips/stop_times filtres sur
 * les route_id RER : IDFM:C01742/43/27/28/29).
 *
 * Volontairement limite aux stations et dessertes (pas de troncons/missions/correspondances a
 * ce stade : l'ampleur du reseau RER - environ 250 stations, embranchements reels complexes,
 * exploitation mixte RATP/SNCF - rend une reconstruction fine de la topologie beaucoup plus
 * lourde que pour 3bis/7bis. Etape 1 d'un travail qui sera complete progressivement.
 *
 * Reutilise une station existante seulement si son nom normalise correspond EXACTEMENT a une
 * station deja en base (ex: Nation, Gare de Lyon, Denfert-Rochereau) : les stations RER dont le
 * nom differe legerement d'une station metro proche (ex: "Châtelet - Les Halles", "Saint-Michel
 * Notre-Dame") sont creees a part, a relier plus tard via des Correspondance, plutot que fusionnees
 * a l'aveugle sur une simple ressemblance de nom.
 */
#[AsCommand(name: 'app:importer-lignes-rer', description: 'Cree les lignes RER (A-E) et leurs stations/dessertes reelles depuis un CSV GTFS pre-extrait')]
class ImporterLignesRerCommand extends Command
{
    private const LIGNES = [
        'A' => 'eb2132',
        'B' => '5091cb',
        'C' => 'ffcc30',
        'D' => '008b5b',
        'E' => 'b94e9a',
    ];

    /**
     * Materiel roulant reel par ligne : [label, annee_debut, annee_fin (null si toujours en service)].
     */
    private const MATERIEL = [
        'A' => [['MS 61', '1970-01-01', '2016-12-31'], ['MI84', '1997-01-01', null], ['MI09', '2011-01-01', null]],
        'B' => [['MS 61', '1970-01-01', '2016-12-31'], ['MI79', '1980-01-01', null], ['RERng', '2025-01-01', null]],
        'C' => [['Z2N', '1988-01-01', null]],
        'D' => [['Z2N', '1987-01-01', null]],
        'E' => [['MI2N', '1999-01-01', null], ['RERng', '2023-01-01', null]],
    ];

    /** @var array<string, Station> cache nom-normalise => Station (existantes + nouvellement creees) */
    private array $stationsCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LigneRepository $ligneRepository,
        private readonly StationRepository $stationRepository,
        private readonly TypeTransportRepository $typeTransportRepository,
        private readonly GestionnaireRepository $gestionnaireRepository,
        private readonly MaterielRepository $materielRepository,
        private readonly TypeMaterielRepository $typeMaterielRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du CSV ligne,station (stations_rer.csv)');
    }

    private function normaliser(string $label): string
    {
        $label = str_replace(['—', '–'], '-', $label);
        $label = \Normalizer::normalize($label, \Normalizer::FORM_D);
        $label = preg_replace('/\p{Mn}/u', '', $label);
        $label = strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label);

        return trim($label);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $typeTransportRer = $this->typeTransportRepository->findOneBy(['label' => 'RER']);
        $sncf = $this->gestionnaireRepository->findOneBy(['label' => 'SNCF']);
        if (null === $typeTransportRer || null === $sncf) {
            $io->error('Type de transport "RER" ou gestionnaire "SNCF" introuvable : lancer d\'abord la migration/le peuplement de reference.');

            return Command::FAILURE;
        }

        $ferraille = $this->typeMaterielRepository->findOneBy(['label' => 'ferraille']);

        // ---- Lignes RER ----
        $lignes = [];
        foreach (self::LIGNES as $label => $couleur) {
            $ligne = $this->ligneRepository->findOneBy(['label' => $label]);
            if (null === $ligne) {
                $ligne = new Ligne();
                $ligne->setLabel($label);
                $this->entityManager->persist($ligne);
            }
            $ligne->setCouleur($couleur);
            $ligne->setTypeTransport($typeTransportRer);
            $ligne->setGestionnaire($sncf);
            $lignes[$label] = $ligne;
        }

        // ---- Materiel roulant reel ----
        foreach (self::MATERIEL as $ligneLabel => $materiels) {
            foreach ($materiels as [$materielLabel, $debut, $fin]) {
                $materiel = $this->materielRepository->findOneBy(['label' => $materielLabel]);
                if (null === $materiel) {
                    $materiel = new Materiel();
                    $materiel->setLabel($materielLabel);
                    $materiel->setTypeMateriel($ferraille);
                    $this->entityManager->persist($materiel);
                }
                $materielLigne = new MaterielLigne();
                $materielLigne->setLigne($lignes[$ligneLabel]);
                $materielLigne->setMateriel($materiel);
                $materielLigne->setArrivee(new \DateTime($debut));
                $materielLigne->setFin(null !== $fin ? new \DateTime($fin) : null);
                $this->entityManager->persist($materielLigne);
            }
        }

        // ---- Stations existantes : cache par nom normalise, pour reutilisation exacte uniquement ----
        foreach ($this->stationRepository->findAll() as $station) {
            $this->stationsCache[$this->normaliser((string) $station->getLabel())] = $station;
        }

        // ---- Stations + dessertes RER ----
        $fh = fopen($input->getArgument('fichier'), 'r');
        fgetcsv($fh);
        $nbNouvellesStations = 0;
        $nbDessertes = 0;
        while (($row = fgetcsv($fh)) !== false) {
            [$ligneLabel, $nomStation] = $row;
            $ligne = $lignes[$ligneLabel] ?? null;
            if (null === $ligne) {
                continue;
            }

            $cle = $this->normaliser($nomStation);
            $station = $this->stationsCache[$cle] ?? null;
            if (null === $station) {
                $station = new Station();
                $station->setLabel($nomStation);
                $this->entityManager->persist($station);
                $this->stationsCache[$cle] = $station;
                ++$nbNouvellesStations;
            }

            $desserte = new Desserte();
            $desserte->setStation($station);
            $desserte->setLigne($ligne);
            $this->entityManager->persist($desserte);
            ++$nbDessertes;
        }
        fclose($fh);

        $this->entityManager->flush();

        $io->success(sprintf(
            '5 lignes RER creees/mises a jour, %d nouvelles stations, %d dessertes.',
            $nbNouvellesStations,
            $nbDessertes
        ));

        return Command::SUCCESS;
    }
}
