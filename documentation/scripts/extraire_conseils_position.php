<?php

/**
 * Extrait, depuis positionnement-dans-la-rame.csv (IDFM), le conseil de positionnement dans la
 * rame pour chaque triplet (ligne, arret de depart, cible), en resolvant :
 *  - l'arret de depart (from_id, "stop_point" GTFS) vers sa ZdC via stops.txt (parent_station) ;
 *  - la cible : soit un AccId direct (to_type=access_point, meme identifiant que acces.csv/
 *    acces_entrees.csv), soit un simple libelle (to_type=stop_point, correspondance).
 *
 * Le feed GTFS complet (~1,3 Go, documentation/IDFM-gtfs/) n'est jamais commit (.gitignore) : ce
 * petit extrait est ce que app:construire-positions-rame utilise en production.
 *
 * Le rattachement de la Ligne se fait par LABEL (ex: "7", "A"), pas par codeExterne : verifie que
 * le codeExterne stocke sur nos Ligne de metro est incoherent avec le GTFS actuel (ex: notre ligne
 * "7" pointe vers C00312, qui correspond dans le GTFS courant a une ligne de BUS renommee "6402
 * (ex 7)", pas a la ligne 7 du metro - route_id C01377). Seules 18 lignes sont couvertes par ce
 * jeu de donnees (metro 1-14+3B+7B, RER A/B), sans ambiguite de label possible dans ce perimetre.
 *
 * Sortie : documentation/scripts/donnees-extraites/conseils_position.csv
 * Colonnes : zdc,ligneLabel,destination,accId,labelPosition,position,positionMax,equipement
 */

$gtfsDir = 'documentation/IDFM-gtfs/';
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

$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['zdc', 'ligneLabel', 'destination', 'accId', 'labelPosition', 'position', 'positionMax', 'equipement']);

$nb = 0;
$nbSansZdc = 0;
foreach (lireCsv($gtfsDir . 'positionnement-dans-la-rame.csv', ';') as $row) {
    $zdc = $stopVersZdc[$row['from_id']] ?? null;
    if (null === $zdc) {
        ++$nbSansZdc;
        continue;
    }

    $accId = 'access_point' === $row['to_type'] ? $row['to_id'] : '';

    fputcsv($fichier, [
        $zdc,
        $row['line_name'],
        $row['to_name'],
        $accId,
        $row['position_average'],
        $row['position'],
        $row['position_max'],
        $row['equipment_type'],
    ]);
    ++$nb;
}
fclose($fichier);

echo "$nb conseils ecrits dans $sortie ($nbSansZdc ignores : arret de depart sans ZdC).\n";
