<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe Station::zoneTarifaire depuis arrets-transporteur.csv (ArTFareZone), rattache par
 * relations.csv (ArTId -> ZdCId -> Station::codeExterne), meme mecanisme deja verifie fiable pour
 * PoleEchange/EquipementArret. Propriete du lieu (pas de la ligne empruntee) : contrairement aux
 * autres champs du referentiel ArT (accessibilite/signalisation, deplaces sur Desserte car ils
 * dependent de la ligne - voir ImporterAccessibiliteDessertesCommand), la zone tarifaire ne varie
 * pas selon la ligne, donc reste sur Station.
 *
 * PIEGES DECOUVERTS (2026-08-20), tous deux des collisions de nom au niveau du referentiel source
 * (ex: "Les Sablons" existe a la fois comme station de metro a Neuilly-sur-Seine (zone 1) ET comme
 * arret de bus a Ecquevilly, a plus de 30km, zone 5) :
 * 1. Un ZdCId peut regrouper des ArT de villes differentes - 873/13643 Station concernees (6.4%),
 *    detectable en comparant les ArTTown d'un meme ZdCId.
 * 2. Plus vicieux : un ZdCId n'ayant qu'UN SEUL ArT peut quand meme etre le mauvais ArT (aucune
 *    incoherence de ville a detecter, un seul son de cloche) - repere en verifiant "Les Sablons"
 *    apres le premier filtre : toujours zone 5 alors que la vraie station est a Neuilly. Detecte
 *    ici en comparant la position geographique de l'ArT (ArTGeopoint) a celle de notre propre
 *    Station : au-dela de 2km d'ecart, le rattachement est juge non fiable et ignore.
 * Meme discipline que le reste du projet (jamais de rattachement ambigu, ex. le tagging Guimard ou
 * les correspondances par nom) : mieux vaut une zone tarifaire manquante qu'une zone fausse.
 *
 * LIMITE CONNUE ET ACCEPTEE : "Les Sablons" (id=4 en local) reste incorrectement a zone 5 malgre
 * les deux verifications ci-dessus, car cette Station n'a PAS de coordonnees (latitude/longitude
 * NULL - phenomene "Stations dupliquees" deja documente, TODO.md, ~570 Station concernees) : le
 * controle de distance ne peut alors pas s'appliquer, et cet unique ArT (aucune incoherence de
 * ville a detecter, un seul son de cloche) est accepte tel quel. Cas residuel rare (intersection de
 * "un seul ArT pour ce ZdCId" ET "ce ZdCId est faux" ET "pas de coordonnees pour verifier") - ne
 * sera resolu qu'en comblant les coordonnees manquantes ou en fusionnant les Stations dupliquees
 * (tache id=10, deliberement hors perimetre).
 */
#[AsCommand(name: 'app:importer-zone-tarifaire', description: "Importe Station::zoneTarifaire depuis arrets-transporteur.csv")]
class ImporterZoneTarifaireCommand extends Command
{
    private const ARRETS_CSV = 'documentation/scripts/donnees-extraites/arrets-transporteur.csv';
    private const RELATIONS_CSV = 'documentation/scripts/donnees-extraites/relations.csv';
    private const DISTANCE_MAX_METRES = 2000.0;
    private const COS_LATITUDE_IDF = 0.6577; // cos(48.85°), reference pour approximer les distances en Ile-de-France

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    private function distanceMetres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dx = ($lon2 - $lon1) * 111320 * self::COS_LATITUDE_IDF;
        $dy = ($lat2 - $lat1) * 111320;

        return sqrt($dx ** 2 + $dy ** 2);
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

        $io->section('Lecture de relations.csv (ArTId => ZdCId)...');
        $zdcIdParArtId = [];
        foreach ($this->lireCsv(self::RELATIONS_CSV) as $ligne) {
            if ('' === $ligne['ArTId'] || '' === $ligne['ZdCId']) {
                continue;
            }
            $zdcIdParArtId[$ligne['ArTId']] = $ligne['ZdCId'];
        }

        $stationsParCode = [];
        foreach ($connexion->executeQuery('SELECT code_externe, id, latitude, longitude FROM station WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $stationsParCode[$row['code_externe']] = [
                'id' => (int) $row['id'],
                'latitude' => null !== $row['latitude'] ? (float) $row['latitude'] : null,
                'longitude' => null !== $row['longitude'] ? (float) $row['longitude'] : null,
            ];
        }

        $io->section('Lecture de '.self::ARRETS_CSV.' (regroupe par ZdCId pour detecter les collisions de ville)...');
        $artsParZdcId = [];
        foreach ($this->lireCsv(self::ARRETS_CSV) as $ligne) {
            if ('' === $ligne['ArTFareZone']) {
                continue;
            }
            $zdcId = $zdcIdParArtId[$ligne['ArTId']] ?? null;
            if (null === $zdcId) {
                continue;
            }
            [$latitude, $longitude] = array_map('trim', explode(',', $ligne['ArTGeopoint']));
            $artsParZdcId[$zdcId][] = [
                'ville' => $ligne['ArTTown'],
                'zone' => (int) $ligne['ArTFareZone'],
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        }

        $zoneParStation = [];
        $nbIgnoresVilleAmbigue = 0;
        $nbIgnoresTropLoin = 0;
        foreach ($artsParZdcId as $zdcId => $arts) {
            $station = $stationsParCode[$zdcId] ?? null;
            if (null === $station) {
                continue;
            }
            $villesDistinctes = array_unique(array_column($arts, 'ville'));
            if (\count($villesDistinctes) > 1) {
                ++$nbIgnoresVilleAmbigue;
                continue;
            }
            $art = $arts[0];
            if (null !== $station['latitude'] && null !== $station['longitude']) {
                $distance = $this->distanceMetres($station['latitude'], $station['longitude'], $art['latitude'], $art['longitude']);
                if ($distance > self::DISTANCE_MAX_METRES) {
                    ++$nbIgnoresTropLoin;
                    continue;
                }
            }
            $zoneParStation[$station['id']] = $art['zone'];
        }
        $io->info(sprintf(
            '%d Station avec une zone tarifaire fiable (%d ZdCId ignores : ArT de villes differentes, %d ignores : ArT a plus de %.0fm de la Station).',
            count($zoneParStation),
            $nbIgnoresVilleAmbigue,
            $nbIgnoresTropLoin,
            self::DISTANCE_MAX_METRES,
        ));

        $io->section('Mise a jour de la base...');
        $nb = 0;
        foreach ($zoneParStation as $stationId => $zone) {
            $nb += $connexion->executeStatement('UPDATE station SET zone_tarifaire = ? WHERE id = ?', [$zone, $stationId]);
        }

        $io->success(sprintf('%d Station mises a jour.', $nb));

        return Command::SUCCESS;
    }
}
