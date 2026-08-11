<?php

/**
 * Extrait, pour chaque ZdC, le temps de marche mediane des transferts INTRA-ZdC (memes deux arrets
 * a l'interieur d'une meme Station) depuis transfers.txt — pour rafraichir les Correspondance
 * existantes (metro/tram/RER, ConstruireCorrespondancesInterModesCommand) qui n'avaient qu'une
 * estimation par defaut (distance NULL, TrajetFinder retombe alors sur 3 min).
 *
 * Sortie : documentation/scripts/donnees-extraites/temps_marche_intra_zdc.csv
 * Colonnes : zdc,duree_mediane_secondes,nb_observations
 */

$gtfsDir = 'documentation/IDFM-gtfs/';
$sortie = 'documentation/scripts/donnees-extraites/temps_marche_intra_zdc.csv';
const DUREE_MAX_SECONDES = 1800;

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

echo "Chargement stop_id -> ZdC...\n";
$zdcConnus = [];
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    if ('1' === $row['location_type']) {
        $zdcConnus[str_replace('IDFM:', '', $row['stop_id'])] = true;
    }
}
$stopVersZdc = [];
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    $parent = $row['parent_station'];
    if (str_starts_with($parent, 'IDFM:')) {
        $zdcId = substr($parent, 5);
        if (isset($zdcConnus[$zdcId])) {
            $stopVersZdc[$row['stop_id']] = $zdcId;
        }
    }
}

echo "Lecture de transfers.txt (intra-ZdC uniquement)...\n";
$dureesParZdc = [];
foreach (lireCsv($gtfsDir . 'transfers.txt') as $row) {
    $a = $stopVersZdc[$row['from_stop_id']] ?? null;
    $b = $stopVersZdc[$row['to_stop_id']] ?? null;
    if (null === $a || null === $b || $a !== $b) {
        continue;
    }
    $duree = (int) $row['min_transfer_time'];
    if ($duree <= 0 || $duree > DUREE_MAX_SECONDES) {
        continue;
    }
    $dureesParZdc[$a][] = $duree;
}
echo \count($dureesParZdc) . " ZdC avec au moins un temps de marche intra-ZdC exploitable.\n";

$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['zdc', 'duree_mediane_secondes', 'nb_observations']);
foreach ($dureesParZdc as $zdc => $durees) {
    sort($durees);
    $n = \count($durees);
    $mediane = 1 === $n % 2 ? $durees[(int) floor($n / 2)] : (int) round(($durees[$n / 2 - 1] + $durees[$n / 2]) / 2);
    fputcsv($fichier, [$zdc, $mediane, $n]);
}
fclose($fichier);

echo "Ecrit dans $sortie.\n";
