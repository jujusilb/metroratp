<?php

/**
 * Extrait les correspondances INTER-ZdC (entre deux Stations distinctes chez nous) depuis
 * transfers.txt (GTFS IDFM) : la grande majorite implique un arret de bus (99,9% des paires,
 * voir analyse de session), donc c'est la source qui permet de construire les correspondances
 * bus<->bus / bus<->metro / bus<->rer / bus<->tram qu'aucune commande existante ne couvrait
 * (ConstruireCorrespondancesInterModesCommand se limite volontairement aux modes lourds entre
 * eux et regroupe par STATION, pas par ZdC brut).
 *
 * Filtre les temps de marche superieurs a 30 min (bruit/artefacts, pas de vraies correspondances
 * pietonnes) et agrege par mediane quand plusieurs paires de stop_id (transporteur) resolvent
 * vers la meme paire de ZdC.
 *
 * Sortie : documentation/scripts/donnees-extraites/correspondances_inter_zdc.csv
 * Colonnes : zdc_a,zdc_b,duree_mediane_secondes,nb_observations
 */

$gtfsDir = 'documentation/IDFM-gtfs/';
$sortie = 'documentation/scripts/donnees-extraites/correspondances_inter_zdc.csv';
const DUREE_MAX_SECONDES = 1800; // 30 min : au-dela, plus une correspondance qu'un artefact GTFS

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
echo \count($stopVersZdc) . " stops resolus vers un ZdC.\n";

echo "Lecture de transfers.txt et agregation par paire de ZdC...\n";
$dureesParPaire = [];
$total = 0;
foreach (lireCsv($gtfsDir . 'transfers.txt') as $row) {
    ++$total;
    $a = $stopVersZdc[$row['from_stop_id']] ?? null;
    $b = $stopVersZdc[$row['to_stop_id']] ?? null;
    if (null === $a || null === $b || $a === $b) {
        continue;
    }
    $duree = (int) $row['min_transfer_time'];
    if ($duree <= 0 || $duree > DUREE_MAX_SECONDES) {
        continue;
    }
    $paire = $a < $b ? "$a|$b" : "$b|$a";
    $dureesParPaire[$paire][] = $duree;
}
echo "$total lignes lues, " . \count($dureesParPaire) . " paires de ZdC retenues.\n";

$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['zdc_a', 'zdb_b', 'duree_mediane_secondes', 'nb_observations']);
foreach ($dureesParPaire as $paire => $durees) {
    [$a, $b] = explode('|', $paire);
    sort($durees);
    $n = \count($durees);
    $mediane = 1 === $n % 2 ? $durees[(int) floor($n / 2)] : (int) round(($durees[$n / 2 - 1] + $durees[$n / 2]) / 2);
    fputcsv($fichier, [$a, $b, $mediane, $n]);
}
fclose($fichier);

echo "Ecrit dans $sortie.\n";
