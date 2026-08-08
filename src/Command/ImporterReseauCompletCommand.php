<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Gestionnaire;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\TypeTransport;
use App\Repository\GestionnaireRepository;
use App\Repository\LigneRepository;
use App\Repository\StationRepository;
use App\Repository\TypeTransportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe la totalite du reseau de transport francilien (bus, car, tramway, + toute ligne "rail"
 * non deja couverte par le RER/metro : Transilien, funiculaire, telepherique...) a partir d'un
 * CSV (route_id,ligne_nom,couleur,mode,submode,operateur,zdc_id,zdc_nom) genere par
 * documentation/scripts/extraire_reseau_complet.py depuis le GTFS complet IDFM croise avec le
 * referentiel officiel des lignes.
 *
 * Volontairement limite aux lignes/stations/dessertes (pas de troncons/missions : a cette echelle
 * - plus de 1400 lignes, ~14000 stations - reconstruire une topologie fine ligne par ligne n'est
 * pas envisageable en un seul passage).
 *
 * Idempotent : chaque Ligne/Station est identifiee par un code externe stable (route_id / ZdCId
 * IDFM), pas par son label (de nombreuses lignes de bus d'operateurs differents partagent le
 * meme numero, et de nombreuses communes ont un arret "Mairie" ou "Eglise" qui ne sont pas le
 * meme endroit : un rapprochement par nom serait incorrect a cette echelle).
 *
 * IMPORTANT : ne fait JAMAIS de rapprochement par nom avec les stations metro/RER deja en base
 * (contrairement a app:peupler-lignes-bis / app:importer-lignes-rer, ou l'espace de noms est
 * restreint et verifiable a la main). A l'echelle regionale complete, des noms de lieux generiques
 * ("Les Sablons", "Concorde"...) reapparaissent dans des communes sans rapport : un essai anterieur
 * de cette commande a par erreur relie ainsi des stations parisiennes a des lieux-dits homonymes a
 * l'autre bout de la region. Chaque ZdC recoit systematiquement sa propre Station (identifiee par
 * son codeExterne), quitte a dupliquer temporairement une station deja connue sous un autre nom :
 * un eventuel rapprochement ulterieur devra se faire sur un critere fiable (proximite geographique
 * reelle, pas le nom).
 */
#[AsCommand(name: 'app:importer-reseau-complet', description: 'Importe tout le reseau IDF (bus/car/tram/train) depuis le CSV extrait du GTFS complet')]
class ImporterReseauCompletCommand extends Command
{
    private const MODE_VERS_TYPE_TRANSPORT = [
        'bus' => 'Bus',
        'tram' => 'Tramway',
        'rail' => 'Train',
        'metro' => 'Métro',
        'funicular' => 'Funiculaire',
        'cableway' => 'Téléphérique',
    ];

    /** @var array<string, TypeTransport> */
    private array $typeTransportCache = [];

    /** @var array<string, Gestionnaire> */
    private array $gestionnaireCache = [];

    /** @var array<string, Ligne> cle = codeExterne (route_id) */
    private array $ligneCache = [];

    /** @var array<string, Station> cle = codeExterne (ZdCId) */
    private array $stationParCode = [];

    /** @var array<string, true> cle = "stationId-ligneId", pour ne jamais dupliquer une desserte (import rejouable/interrompu) */
    private array $dessertesExistantes = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LigneRepository $ligneRepository,
        private readonly StationRepository $stationRepository,
        private readonly TypeTransportRepository $typeTransportRepository,
        private readonly GestionnaireRepository $gestionnaireRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du CSV reseau_complet.csv');
    }

    private function obtenirTypeTransport(string $mode): ?TypeTransport
    {
        $label = self::MODE_VERS_TYPE_TRANSPORT[$mode] ?? null;
        if (null === $label) {
            return null;
        }
        if (!isset($this->typeTransportCache[$label])) {
            $type = $this->typeTransportRepository->findOneBy(['label' => $label]);
            if (null === $type) {
                $type = new TypeTransport();
                $type->setLabel($label);
                $this->entityManager->persist($type);
            }
            $this->typeTransportCache[$label] = $type;
        }

        return $this->typeTransportCache[$label];
    }

    private function obtenirGestionnaire(string $nom): Gestionnaire
    {
        if (!isset($this->gestionnaireCache[$nom])) {
            $gestionnaire = $this->gestionnaireRepository->findOneBy(['label' => $nom]);
            if (null === $gestionnaire) {
                $gestionnaire = new Gestionnaire();
                $gestionnaire->setLabel(mb_substr($nom, 0, 100));
                $this->entityManager->persist($gestionnaire);
            }
            $this->gestionnaireCache[$nom] = $gestionnaire;
        }

        return $this->gestionnaireCache[$nom];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->ligneRepository->findAll() as $ligne) {
            if (null !== $ligne->getCodeExterne()) {
                $this->ligneCache[$ligne->getCodeExterne()] = $ligne;
            }
        }
        foreach ($this->stationRepository->findAll() as $station) {
            if (null !== $station->getCodeExterne()) {
                $this->stationParCode[$station->getCodeExterne()] = $station;
            }
        }
        $conn = $this->entityManager->getConnection();
        foreach ($conn->executeQuery('SELECT station_id, ligne_id FROM desserte')->iterateAssociative() as $row) {
            $this->dessertesExistantes[$row['station_id'].'-'.$row['ligne_id']] = true;
        }

        $fh = fopen($input->getArgument('fichier'), 'r');
        $header = fgetcsv($fh);
        $nbNouvellesLignes = 0;
        $nbNouvellesStations = 0;
        $nbDessertes = 0;
        $nbIgnorees = 0;
        $compteur = 0;

        while (($row = fgetcsv($fh)) !== false) {
            [$routeId, $ligneNom, $couleur, $mode, , $operateur, $zdcId, $zdcNom] = $row;

            $typeTransport = $this->obtenirTypeTransport($mode);
            if (null === $typeTransport) {
                ++$nbIgnorees;
                continue;
            }

            $ligne = $this->ligneCache[$routeId] ?? null;
            if (null === $ligne) {
                $ligne = new Ligne();
                $ligne->setCodeExterne($routeId);
                $ligne->setLabel(mb_substr($ligneNom, 0, 20));
                $ligne->setCouleur($couleur);
                $ligne->setTypeTransport($typeTransport);
                $ligne->setGestionnaire($this->obtenirGestionnaire($operateur));
                $this->entityManager->persist($ligne);
                $this->ligneCache[$routeId] = $ligne;
                ++$nbNouvellesLignes;
            }

            $station = $this->stationParCode[$zdcId] ?? null;
            if (null === $station) {
                // Toujours une nouvelle Station, jamais de rapprochement par nom avec l'existant
                // (voir le docblock de la classe) : seul le ZdCId identifie fiablement un lieu.
                $station = new Station();
                $station->setLabel($zdcNom);
                $station->setCodeExterne($zdcId);
                $this->entityManager->persist($station);
                $this->stationParCode[$zdcId] = $station;
                ++$nbNouvellesStations;
            }

            $cleDesserte = $station->getId().'-'.$ligne->getId();
            if (null !== $station->getId() && null !== $ligne->getId() && isset($this->dessertesExistantes[$cleDesserte])) {
                // Deja creee lors d'une execution precedente interrompue (station et ligne
                // existaient donc deja avant cette execution) : on ne duplique pas.
                continue;
            }

            $desserte = new Desserte();
            $desserte->setStation($station);
            $desserte->setLigne($ligne);
            $this->entityManager->persist($desserte);
            ++$nbDessertes;

            if (0 === (++$compteur % 3000)) {
                $this->entityManager->flush();
                $io->writeln(sprintf('  ... %d lignes traitees', $compteur));
            }
        }
        fclose($fh);

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d nouvelles lignes, %d nouvelles stations, %d dessertes (%d lignes ignorees, mode inconnu).',
            $nbNouvellesLignes,
            $nbNouvellesStations,
            $nbDessertes,
            $nbIgnorees
        ));

        return Command::SUCCESS;
    }
}
