<?php

namespace App\Command;

use App\Entity\EquipementArret;
use App\Entity\Station;
use App\Repository\EquipementArretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe ecarts-arrets-referentiel-et-openstreetmap.csv (equipements OSM par arret physique -
 * fauteuil roulant, banc, poubelle, eclairage, abri, bande tactile - introuvables ailleurs dans le
 * projet) et rattache chaque arret (ArT) a sa Station via relations.csv (ArTId -> ZdCId ->
 * Station::codeExterne), meme mecanisme deja verifie fiable pour PoleEchange (voir
 * ImporterPolesEchangeCommand) et Acces (pathways.txt).
 *
 * Un meme ArTId peut apparaitre plusieurs fois dans le CSV source (plusieurs elements OSM proches
 * d'un meme arret, parfois avec des tags contradictoires) : on ne garde que la ligne a la plus
 * petite "Distance (m)" (le rapprochement OSM/referentiel le plus fiable pour cet arret) plutot
 * que de risquer un merge incoherent de plusieurs valeurs.
 *
 * Cle d'import stable : ArTId (unique en base) - rejouable sans creer de doublons.
 */
#[AsCommand(name: 'app:importer-equipements-arrets', description: "Importe les equipements OSM par arret physique (ecarts-arrets-referentiel-et-openstreetmap.csv), rattaches a Station via relations.csv")]
class ImporterEquipementsArretsCommand extends Command
{
    private const ECARTS_CSV = 'documentation/scripts/donnees-extraites/ecarts-arrets-referentiel-et-openstreetmap.csv';
    private const RELATIONS_CSV = 'documentation/scripts/donnees-extraites/relations.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EquipementArretRepository $equipementArretRepository,
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

    /**
     * "yes"/"limited"/"designated" -> true, "no" -> false, vide ou valeur ambigue (ex: "yes;no",
     * plusieurs elements OSM contradictoires concatenes par la source) -> null (information
     * inconnue, pas "non equipe").
     */
    private function versBool(string $valeurOsm): ?bool
    {
        return match ($valeurOsm) {
            'yes', 'limited', 'designated' => true,
            'no' => false,
            default => null,
        };
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Lecture de relations.csv (ArTId => ZdCId)...');
        $zdcIdParArtId = [];
        foreach ($this->lireCsv(self::RELATIONS_CSV) as $ligne) {
            if ('' === $ligne['ArTId'] || '' === $ligne['ZdCId']) {
                continue;
            }
            $zdcIdParArtId[$ligne['ArTId']] = $ligne['ZdCId'];
        }
        $io->info(sprintf('%d couples ArTId => ZdCId distincts trouves.', count($zdcIdParArtId)));

        $stationIdParCode = [];
        foreach ($connexion->executeQuery('SELECT code_externe, id FROM station WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $stationIdParCode[$row['code_externe']] = (int) $row['id'];
        }

        $io->section('Lecture de '.self::ECARTS_CSV.' (dedoublonnage par ArTId, plus petite distance conservee)...');
        $lignesParArtId = [];
        foreach ($this->lireCsv(self::ECARTS_CSV) as $ligne) {
            $artId = $ligne['ArTId'];
            $distance = '' === $ligne['Distance (m)'] ? \PHP_INT_MAX : (int) round((float) $ligne['Distance (m)']);
            if (!isset($lignesParArtId[$artId]) || $distance < $lignesParArtId[$artId]['distance']) {
                $lignesParArtId[$artId] = ['ligne' => $ligne, 'distance' => $distance];
            }
        }
        $io->info(sprintf('%d ArT distincts.', count($lignesParArtId)));

        $io->section('Import...');
        $nbCrees = 0;
        $nbMaj = 0;
        $nbSansStation = 0;
        $i = 0;
        foreach ($lignesParArtId as $artId => ['ligne' => $ligne]) {
            $zdcId = $zdcIdParArtId[$artId] ?? null;
            $stationId = null !== $zdcId ? ($stationIdParCode[$zdcId] ?? null) : null;
            if (null === $stationId) {
                ++$nbSansStation;
                continue;
            }

            $equipement = $this->equipementArretRepository->findOneBy(['artId' => (int) $artId]) ?? new EquipementArret();
            $estNouveau = null === $equipement->getId();

            [$latitude, $longitude] = array_map('trim', explode(',', $ligne['ArTGeopoint']));

            $equipement->setArtId((int) $artId);
            $equipement->setNom($ligne['ArTName']);
            $equipement->setVille('' !== $ligne['ArTTown'] ? $ligne['ArTTown'] : null);
            $equipement->setLatitude((float) $latitude);
            $equipement->setLongitude((float) $longitude);
            $equipement->setAccessibleFauteuilRoulant($this->versBool($ligne['wheelchair']));
            $equipement->setBanc($this->versBool($ligne['bench']));
            $equipement->setPoubelle($this->versBool($ligne['bin']));
            $equipement->setEclairage($this->versBool($ligne['lit']));
            $equipement->setAbri($this->versBool($ligne['shelter']));
            $equipement->setBandeTactile($this->versBool($ligne['tactile_paving']));
            $equipement->setDistanceReferentielOsm('' !== $ligne['Distance (m)'] ? (int) round((float) $ligne['Distance (m)']) : null);
            $equipement->setStation($this->entityManager->getReference(Station::class, $stationId));

            if ($estNouveau) {
                $this->entityManager->persist($equipement);
                ++$nbCrees;
            } else {
                ++$nbMaj;
            }

            if (0 === ++$i % 500) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d EquipementArret crees, %d mis a jour (%d ArT sans Station correspondante, ignores).',
            $nbCrees,
            $nbMaj,
            $nbSansStation,
        ));

        return Command::SUCCESS;
    }
}
