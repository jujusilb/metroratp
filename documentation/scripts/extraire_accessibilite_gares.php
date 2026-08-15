<?php

/**
 * Extrait accessibilite-en-gare.csv (niveau d'accessibilite PMR de 459 gares) en resolvant son
 * stop_point_id (GTFS StopPlace monomodal, ex: "stop_point:IDFM:monomodalStopPlace:47915") vers
 * la ZdC de la gare via stops.txt (parent_station) - le CSV source ne donne aucune cle directe
 * vers le referentiel ZdC utilise partout ailleurs dans ce projet (Station::codeExterne).
 *
 * Le feed GTFS complet (~1,3 Go, documentation/IDFM-gtfs/) n'est jamais commit (.gitignore) : ce
 * petit extrait est ce que app:importer-accessibilite-gares utilise en production.
 *
 * Sortie : documentation/scripts/donnees-extraites/accessibilite_gares.csv
 * Colonnes : zdc,niveau,commentaire
 */
$gtfsDir = 'documentation/IDFM-gtfs/';
$sortie = 'documentation/scripts/donnees-extraites/accessibilite_gares.csv';

function lireCsv(string $chemin, string $separateur = ','): \Generator
{
    $f = fopen($chemin, 'r');
    $header = fgetcsv($f, 0, $separateur);
    $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
    $idx = array_flip($header);
    while (($row = fgetcsv($f, 0, $separateur)) !== false) {
        $assoc = [];
        foreach ($idx as $col => $i) {
            $assoc[$col] = $row[$i] ?? '';
        }
        yield $assoc;
    }
    fclose($f);
}

echo "Chargement des stop_id -> parent_station (ZdC) depuis stops.txt...\n";
$zdcParStopId = [];
foreach (lireCsv($gtfsDir.'stops.txt') as $row) {
    $zdcParStopId[$row['stop_id']] = str_replace('IDFM:', '', $row['parent_station']);
}
echo count($zdcParStopId)." stops charges.\n";

$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['zdc', 'niveau', 'commentaire']);

$nb = 0;
$nbIgnores = 0;
foreach (lireCsv($gtfsDir.'accessibilite-en-gare.csv', ';') as $row) {
    $stopId = str_replace('stop_point:', '', $row['stop_point_id']);
    $zdc = $zdcParStopId[$stopId] ?? null;
    if (null === $zdc || '' === $zdc) {
        ++$nbIgnores;
        continue;
    }

    fputcsv($fichier, [$zdc, $row['accessibility_level_name'], $row['commentaire']]);
    ++$nb;
}
fclose($fichier);

echo "$nb lignes ecrites dans $sortie ($nbIgnores ignorees : pas de ZdC resolue).\n";
