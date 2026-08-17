<?php

/**
 * Extrait la liste des stations reelles desservies par les lignes Transilien V/P/R depuis le GTFS
 * complet, meme methode que documentation/scripts/extraire_stations_rer.py (deja utilise pour
 * app:importer-lignes-rer) : nom de stop_id directement (pas de resolution ZdC, pas de topologie
 * a ce stade — meme limitation volontaire que l'import RER initial, voir son docblock).
 *
 * Sortie : documentation/scripts/donnees-extraites/stations_transilien_vpr.csv
 * Colonnes : ligne,station (meme format que stations_rer.csv, consomme par un import dedie).
 */

const GTFS_DIR = 'documentation/IDFM-gtfs/csv/';
const SORTIE = 'documentation/scripts/donnees-extraites/stations_transilien_vpr.csv';

const ROUTES = [
    'IDFM:C02711' => 'V',
    'IDFM:C01731' => 'R',
    'IDFM:C01730' => 'P',
];

function lireCsv(string $chemin): \Generator
{
    $f = fopen($chemin, 'r');
    $header = fgetcsv($f);
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $idx = array_flip($header);
    while (($row = fgetcsv($f)) !== false) {
        $assoc = [];
        foreach ($idx as $col => $i) {
            $assoc[$col] = $row[$i] ?? '';
        }
        yield $assoc;
    }
    fclose($f);
}

$stopNames = [];
foreach (lireCsv(GTFS_DIR.'stops.txt') as $row) {
    $stopNames[$row['stop_id']] = $row['stop_name'];
}

$tripRoute = [];
foreach (lireCsv(GTFS_DIR.'trips.txt') as $row) {
    if (isset(ROUTES[$row['route_id']])) {
        $tripRoute[$row['trip_id']] = ROUTES[$row['route_id']];
    }
}
echo count($tripRoute)." trips Transilien V/P/R trouves\n";

$stationsParLigne = [];
foreach (lireCsv(GTFS_DIR.'stop_times.txt') as $row) {
    $ligne = $tripRoute[$row['trip_id']] ?? null;
    if (null === $ligne) {
        continue;
    }
    $nom = $stopNames[$row['stop_id']] ?? null;
    if (null !== $nom) {
        $stationsParLigne[$ligne][$nom] = true;
    }
}

$fichier = fopen(SORTIE, 'w');
fputcsv($fichier, ['ligne', 'station']);
foreach (['V', 'P', 'R'] as $ligne) {
    $stations = array_keys($stationsParLigne[$ligne] ?? []);
    sort($stations);
    echo "=== $ligne : ".count($stations)." stations ===\n";
    foreach ($stations as $station) {
        echo "  $station\n";
        fputcsv($fichier, [$ligne, $station]);
    }
}
fclose($fichier);
echo "\nEcrit dans ".SORTIE."\n";
