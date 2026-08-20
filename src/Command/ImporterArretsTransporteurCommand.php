<?php

namespace App\Command;

use App\Entity\ArretTransporteur;
use App\Entity\Station;
use App\Repository\ArretTransporteurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe arrets-transporteur.csv (referentiel officiel IDFM au niveau ArT - un arret physique
 * d'un operateur donne) et rattache chaque ArT a sa Station via relations.csv (ArTId -> ZdCId ->
 * Station::codeExterne), meme mecanisme deja verifie fiable pour PoleEchange et EquipementArret.
 *
 * A la difference d'EquipementArret (tags OpenStreetMap), l'accessibilite/signalisation ici vient
 * directement du referentiel officiel IDFM - complementaire, pas redondant, meme si 99.99% des ArT
 * d'EquipementArret existent aussi dans ce fichier (verifie avant d'implementer).
 *
 * Cle d'import stable : ArTId (unique en base) - rejouable sans creer de doublons. Contrairement a
 * ecarts-arrets-referentiel-et-openstreetmap.csv, ce fichier n'a aucun ArTId en double (verifie :
 * 52516 lignes, 52516 ArTId distincts).
 */
#[AsCommand(name: 'app:importer-arrets-transporteur', description: "Importe le referentiel officiel des arrets physiques (arrets-transporteur.csv), rattaches a Station via relations.csv")]
class ImporterArretsTransporteurCommand extends Command
{
    private const ARRETS_CSV = 'documentation/scripts/donnees-extraites/arrets-transporteur.csv';
    private const RELATIONS_CSV = 'documentation/scripts/donnees-extraites/relations.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArretTransporteurRepository $arretTransporteurRepository,
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
     * "true" -> true, "false" -> false, "unknown"/"partial" -> null (information inconnue ou trop
     * ambigue pour trancher, pas une absence constatee).
     */
    private function versBool(string $valeur): ?bool
    {
        return match ($valeur) {
            'true' => true,
            'false' => false,
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

        $io->section('Import de '.self::ARRETS_CSV.'...');
        $nbCrees = 0;
        $nbMaj = 0;
        $nbSansStation = 0;
        $i = 0;
        foreach ($this->lireCsv(self::ARRETS_CSV) as $ligne) {
            $artId = $ligne['ArTId'];
            $zdcId = $zdcIdParArtId[$artId] ?? null;
            $stationId = null !== $zdcId ? ($stationIdParCode[$zdcId] ?? null) : null;
            if (null === $stationId) {
                ++$nbSansStation;
                continue;
            }

            $arret = $this->arretTransporteurRepository->findOneBy(['artId' => (int) $artId]) ?? new ArretTransporteur();
            $estNouveau = null === $arret->getId();

            [$latitude, $longitude] = array_map('trim', explode(',', $ligne['ArTGeopoint']));

            $arret->setArtId((int) $artId);
            $arret->setNom($ligne['ArTName']);
            $arret->setVille('' !== $ligne['ArTTown'] ? $ligne['ArTTown'] : null);
            $arret->setType($ligne['ArTType']);
            $arret->setZoneTarifaire('' !== $ligne['ArTFareZone'] ? (int) $ligne['ArTFareZone'] : null);
            $arret->setEstAccessible($this->versBool($ligne['ArTAccessibility']));
            $arret->setSignalisationSonore($this->versBool($ligne['ArTAudibleSignals']));
            $arret->setSignalisationVisuelle($this->versBool($ligne['ArTVisualSigns']));
            $arret->setLatitude((float) $latitude);
            $arret->setLongitude((float) $longitude);
            $arret->setStation($this->entityManager->getReference(Station::class, $stationId));

            if ($estNouveau) {
                $this->entityManager->persist($arret);
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
            '%d ArretTransporteur crees, %d mis a jour (%d ArT sans Station correspondante, ignores).',
            $nbCrees,
            $nbMaj,
            $nbSansStation,
        ));

        return Command::SUCCESS;
    }
}
