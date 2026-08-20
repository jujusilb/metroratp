<?php

namespace App\Command;

use App\Entity\Acces;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Comble le trou "escalator/mât" documente dans TODO.md (GTFS pathways.txt ne distingue pas les
 * modes de cheminement) via un rattachement par proximite geographique a OpenStreetMap - PAS via
 * un identifiant officiel (aucun ne relie Acces a OpenStreetMap, contrairement a codeExterne).
 *
 * Piege deja rencontre en 2026-08-17 (voir TODO.md) : interroger le tag generique "escalator" sur
 * les nœuds d'entree ne trouve presque rien (4 resultats sur toute l'Ile-de-France). Le vrai tagage
 * standard OSM pour un escalier mecanique est "highway=steps"+"conveying=*" (une ligne, pas un
 * point) ; pour un ascenseur, "highway=elevator" (un nœud). Interroge via Overpass API
 * (lz4.overpass-api.de, comme en 2026-08-17) : 1512 escaliers mecaniques (dont seulement 13 taggues
 * conveying=no, donc non fonctionnels) + 1427 ascenseurs sur toute l'Ile-de-France - resultat
 * sauvegarde dans documentation/scripts/donnees-extraites/osm-escaliers-mecaniques-ascenseurs-idf.json
 * pour rester reproductible sans redependre d'Overpass a chaque reimport (mirroirs connus flaky).
 *
 * Rattachement : pour chaque element OSM, l'Acces geolocalise le plus proche (distance vol d'oiseau,
 * approximation equirectangulaire). Teste egalement un rattachement a la Station la plus proche :
 * moins precis (mediane 88m contre 44m pour Acces - les coordonnees de Station ne sont pas
 * systematiquement au niveau de l'entree), abandonne au profit d'Acces.
 *
 * Seuil de confiance (meme discipline que le tagging Guimard, TODO.md) : ignore si l'Acces le plus
 * proche est a plus de SEUIL_METRES, ou si le DEUXIEME Acces le plus proche est presque aussi proche
 * (rattachement ambigu entre deux portes voisines, frequent dans les grandes stations). Sur 2926
 * elements OSM : 695 rattachements confiants retenus, 419 ambigus et ~1800 trop loin ecartes.
 */
#[AsCommand(name: 'app:importer-escaliers-ascenseurs-osm', description: "Rattache par proximite les escaliers mecaniques/ascenseurs OpenStreetMap aux Acces geolocalises")]
class ImporterEscaliersAscenseursOsmCommand extends Command
{
    private const OSM_JSON = 'documentation/scripts/donnees-extraites/osm-escaliers-mecaniques-ascenseurs-idf.json';
    private const SEUIL_METRES = 30.0;
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Lecture des Acces geolocalises...');
        $acces = $connexion->executeQuery('SELECT id, latitude, longitude FROM acces WHERE latitude IS NOT NULL')->fetchAllAssociative();
        $io->info(sprintf('%d Acces geolocalises.', count($acces)));

        $io->section('Lecture de '.self::OSM_JSON.'...');
        $data = json_decode(file_get_contents(self::OSM_JSON), true, flags: \JSON_THROW_ON_ERROR);
        $points = [];
        foreach ($data['elements'] as $element) {
            if ('node' === $element['type']) {
                $points[] = ['lat' => $element['lat'], 'lon' => $element['lon'], 'type' => 'ascenseur'];
                continue;
            }
            if ('way' === $element['type'] && 'no' !== ($element['tags']['conveying'] ?? 'no')) {
                $lats = array_column($element['geometry'], 'lat');
                $lons = array_column($element['geometry'], 'lon');
                $points[] = ['lat' => array_sum($lats) / count($lats), 'lon' => array_sum($lons) / count($lons), 'type' => 'escalator'];
            }
        }
        $io->info(sprintf('%d elements OSM (escaliers mecaniques + ascenseurs).', count($points)));

        $io->section('Rattachement par proximite (seuil '.self::SEUIL_METRES.'m, ignore si ambigu)...');
        $parAcces = []; // id Acces => ['escalator' => bool, 'ascenseur' => bool]
        $nbAmbigus = 0;
        $nbTropLoin = 0;
        foreach ($points as $point) {
            $distances = [];
            foreach ($acces as $unAcces) {
                $distances[] = [$this->distanceMetres($point['lat'], $point['lon'], (float) $unAcces['latitude'], (float) $unAcces['longitude']), (int) $unAcces['id']];
            }
            usort($distances, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

            [$meilleureDistance, $meilleurId] = $distances[0];
            if ($meilleureDistance > self::SEUIL_METRES) {
                ++$nbTropLoin;
                continue;
            }
            $deuxiemeDistance = $distances[1][0];
            if ($deuxiemeDistance <= max($meilleureDistance * 2, $meilleureDistance + 15)) {
                ++$nbAmbigus;
                continue;
            }

            $parAcces[$meilleurId][$point['type']] = true;
        }
        $io->info(sprintf('%d Acces avec au moins un rattachement confiant (%d elements ambigus ignores, %d trop loin de tout Acces).', count($parAcces), $nbAmbigus, $nbTropLoin));

        $io->section('Mise a jour de la base...');
        $nbEscalier = 0;
        $nbAscenseur = 0;
        foreach ($parAcces as $accesId => $trouve) {
            $acces = $this->entityManager->getRepository(Acces::class)->find($accesId);
            if (null === $acces) {
                continue;
            }
            if ($trouve['escalator'] ?? false) {
                $acces->setAEscalierMecanique(true);
                ++$nbEscalier;
            }
            if ($trouve['ascenseur'] ?? false) {
                $acces->setAAscenseur(true);
                ++$nbAscenseur;
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d Acces avec escalier mecanique, %d avec ascenseur.', $nbEscalier, $nbAscenseur));

        return Command::SUCCESS;
    }
}
