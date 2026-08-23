<?php

/**
 * Extrait, depuis positionnement-dans-la-rame.csv (IDFM), le conseil de positionnement dans la
 * rame pour chaque triplet (ligne, arret de depart, cible), en resolvant :
 *  - l'arret de depart (from_id, "stop_point" GTFS) vers sa ZdC via stops.txt (parent_station) ;
 *  - la cible : soit un AccId direct (to_type=access_point, meme identifiant que acces.csv/
 *    acces_entrees.csv), soit un simple libelle (to_type=stop_point, correspondance).
 *
 * Version 2 (2026-08-23) : ajoute le SENS de circulation, perdu par la version precedente qui
 * resolvait from_id directement vers sa ZdC (generique, tous sens confondus). Un stop_point
 * (from_id) est un quai precis, donc un sens precis (verifie : tous les trips qui le desservent
 * partagent le meme direction_id/trip_headsign). Pour chaque from_id, on retrouve UN trip GTFS
 * qui le dessert (stop_times.txt), on recupere son direction_id/trip_headsign (trips.txt), et
 * surtout la ZdC du PROCHAIN arret reel dans ce sens precis - c'est ce qui permet ensuite, dans
 * le calculateur de trajet, de savoir si un conseil correspond bien au sens reellement emprunte
 * (le prochain arret du trajet calcule correspond-il a ce "zdc_suivant" ?) sans avoir a
 * reconstruire un ordre global de la ligne (fragile sur les lignes en maillage, voir
 * documentation/TODO.md).
 *
 * Le feed GTFS complet (~1,3 Go, documentation/IDFM-gtfs/csv/) n'est plus commit (.gitignore) mais
 * est present en local pour cette extraction ponctuelle.
 *
 * Sortie : documentation/scripts/donnees-extraites/conseils_position.csv
 * Colonnes : zdc,ligneLabel,destination,accId,labelPosition,position,positionMax,equipement,
 *            directionId,terminusReel,zdcSuivant
 */

$gtfsDir = 'documentation/IDFM-gtfs/csv/';
$sortie = 'documentation/scripts/donnees-extraites/conseils_position.csv';

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

echo "Lecture de positionnement-dans-la-rame.csv...\n";
$lignesSource = [];
foreach (lireCsv($gtfsDir . 'positionnement-dans-la-rame.csv', ';') as $row) {
    $lignesSource[] = $row;
}
echo count($lignesSource) . " lignes source.\n";

$fromIdsRecherches = array_flip(array_unique(array_map(static fn ($r) => $r['from_id'], $lignesSource)));
echo count($fromIdsRecherches) . " from_id distincts a resoudre.\n";

echo "Association stop_id -> ZdC (stops.txt, parent_station)...\n";
$zdcConnus = [];
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    if ('1' === $row['location_type']) {
        $zdcConnus[str_replace('IDFM:', '', $row['stop_id'])] = true;
    }
}
$stopVersZdc = [];
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    $id = str_replace('IDFM:', '', $row['stop_id']);
    $parent = str_replace('IDFM:', '', $row['parent_station']);
    if (isset($zdcConnus[$parent])) {
        $stopVersZdc[$id] = $parent;
    }
}
echo count($stopVersZdc) . " arrets rattaches a une ZdC.\n";

echo "1ere passe sur stop_times.txt (recherche d'un trip representatif par from_id, ~11,8M lignes)...\n";
$tripRepresentatifParFromId = []; // fromIdCourt => ['tripId' => ..., 'stopSequence' => ...]
$fh = fopen($gtfsDir . 'stop_times.txt', 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
$idx = array_flip($header);
$nbLus = 0;
while (($row = fgetcsv($fh)) !== false) {
    ++$nbLus;
    if (0 === $nbLus % 2000000) {
        echo '  '.($nbLus / 1000000)."M lignes lues...\n";
    }
    $stopId = str_replace('IDFM:', '', $row[$idx['stop_id']]);
    if (!isset($fromIdsRecherches[$stopId]) || isset($tripRepresentatifParFromId[$stopId])) {
        continue;
    }
    $tripRepresentatifParFromId[$stopId] = [
        'tripId' => $row[$idx['trip_id']],
        'stopSequence' => (int) $row[$idx['stop_sequence']],
    ];
}
fclose($fh);
echo count($tripRepresentatifParFromId)." from_id resolus vers un trip representatif (sur ".count($fromIdsRecherches).").\n";

$tripIdsRecherches = array_flip(array_unique(array_map(static fn ($v) => $v['tripId'], $tripRepresentatifParFromId)));
echo count($tripIdsRecherches)." trip_id distincts a relire pour leur sequence complete.\n";

echo "2e passe sur stop_times.txt (sequence complete des trips representatifs)...\n";
$sequenceParTrip = []; // tripId => [stopSequence => stopIdCourt]
$fh = fopen($gtfsDir . 'stop_times.txt', 'r');
fgetcsv($fh);
$nbLus = 0;
while (($row = fgetcsv($fh)) !== false) {
    ++$nbLus;
    if (0 === $nbLus % 2000000) {
        echo '  '.($nbLus / 1000000)."M lignes lues...\n";
    }
    $tripId = $row[$idx['trip_id']];
    if (!isset($tripIdsRecherches[$tripId])) {
        continue;
    }
    $stopId = str_replace('IDFM:', '', $row[$idx['stop_id']]);
    $sequenceParTrip[$tripId][(int) $row[$idx['stop_sequence']]] = $stopId;
}
fclose($fh);

echo "Lecture de trips.txt (direction_id/trip_headsign des trips representatifs)...\n";
$infosParTrip = [];
foreach (lireCsv($gtfsDir . 'trips.txt') as $row) {
    if (isset($tripIdsRecherches[$row['trip_id']])) {
        $infosParTrip[$row['trip_id']] = [
            'directionId' => $row['direction_id'],
            'headsign' => $row['trip_headsign'],
        ];
    }
}

echo "Ecriture de $sortie...\n";
$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['zdc', 'ligneLabel', 'destination', 'accId', 'labelPosition', 'position', 'positionMax', 'equipement', 'directionId', 'terminusReel', 'zdcSuivant']);

$nb = 0;
$nbSansZdc = 0;
$nbSansSens = 0;
foreach ($lignesSource as $row) {
    $zdc = $stopVersZdc[$row['from_id']] ?? null;
    if (null === $zdc) {
        ++$nbSansZdc;
        continue;
    }

    $accId = 'access_point' === $row['to_type'] ? $row['to_id'] : '';

    $directionId = '';
    $terminusReel = '';
    $zdcSuivant = '';
    $representatif = $tripRepresentatifParFromId[$row['from_id']] ?? null;
    if (null !== $representatif) {
        $tripId = $representatif['tripId'];
        $infos = $infosParTrip[$tripId] ?? null;
        if (null !== $infos) {
            $directionId = $infos['directionId'];
            $terminusReel = $infos['headsign'];
        }
        $sequence = $sequenceParTrip[$tripId] ?? [];
        $prochaineSequence = $representatif['stopSequence'] + 1;
        if (isset($sequence[$prochaineSequence])) {
            $zdcSuivant = $stopVersZdc[$sequence[$prochaineSequence]] ?? '';
        }
    }
    if ('' === $directionId) {
        ++$nbSansSens;
    }

    fputcsv($fichier, [
        $zdc,
        $row['line_name'],
        $row['to_name'],
        $accId,
        $row['position_average'],
        $row['position'],
        $row['position_max'],
        $row['equipment_type'],
        $directionId,
        $terminusReel,
        $zdcSuivant,
    ]);
    ++$nb;
}
fclose($fichier);

echo "$nb conseils ecrits dans $sortie ($nbSansZdc ignores : arret de depart sans ZdC, $nbSansSens sans sens resolu).\n";
