<?php

/**
 * Extrait les coordonnees geographiques reelles (WGS84) de chaque ZdC depuis stops.txt (GTFS
 * IDFM, location_type=1) - le feed GTFS complet (~1,3 Go, documentation/IDFM-gtfs/) n'est jamais
 * commit (.gitignore), donc ce petit extrait est ce que les commandes utilisent en production.
 *
 * Sortie : documentation/scripts/donnees-extraites/zdc_coordonnees.csv
 * Colonnes : zdc,latitude,longitude
 */

$gtfsDir = 'documentation/IDFM-gtfs/';
$sortie = 'documentation/scripts/donnees-extraites/zdc_coordonnees.csv';

function lireCsv(string $chemin): \Generator
{
    $f = fopen($chemin, 'r');
    $header = fgetcsv($f);
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

$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['zdc', 'latitude', 'longitude']);

$nb = 0;
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    if ('1' === $row['location_type']) {
        fputcsv($fichier, [str_replace('IDFM:', '', $row['stop_id']), $row['stop_lat'], $row['stop_lon']]);
        ++$nb;
    }
}
fclose($fichier);

echo "$nb ZdC ecrits dans $sortie.\n";
