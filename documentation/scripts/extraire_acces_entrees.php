<?php

/**
 * Extrait, pour chaque acces/entree GTFS (stops.txt, location_type=2 = "StopPlaceEntrance"), son
 * rattachement a une ZdC (parent_station), son libelle/numero officiels - en preferant acces.csv
 * (dataset "acces", data.iledefrance-mobilites.fr, AccName/AccShortName) quand l'acces y figure,
 * sinon en repli sur stop_name/stop_code du GTFS (~7 acces absents de l'export CSV) - et ses
 * coordonnees geographiques (stop_lat/stop_lon, directement sur la ligne GTFS de l'acces, pas
 * besoin du champ AccGeopoint de acces.csv). Ajoute aussi AccIsEntry/AccIsExit (utilisable en
 * entree/sortie), seuls champs de acces.csv au-dela du libelle/numero qui se sont averes non-vides
 * et exploitables (AccDescription verifiee : ~45% remplie mais quasi exclusivement du texte
 * generique "Source IDFM", pas de contenu specifique — pas retenue).
 *
 * D'autres pistes de remplissage verifiees et ecartees car le champ source est vide sur 100% des
 * entrees (verifie le 2026-08-17) : wheelchair_boarding, platform_code, stop_desc, level_id,
 * stop_access, stop_url de stops.txt, et le repli stop_code (jamais atteint en pratique : le
 * fallback `?? $row['stop_code']` ne se declenche que si l'accId est absent de acces.csv, pas si
 * son numero y est vide — et stop_code est de toute facon vide partout). Le numero manquant sur
 * ~42% des acces (1047/2515) est donc une vraie absence dans la donnee source IDFM, pas une
 * extraction incomplete de notre cote.
 *
 * Le feed GTFS complet (~1,3 Go, documentation/IDFM-gtfs/) n'est jamais commit (.gitignore) : ce
 * petit extrait est ce que app:construire-acces-sorties utilise en production.
 *
 * Sortie : documentation/scripts/donnees-extraites/acces_entrees.csv
 * Colonnes : accId,zdc,label,numero,lat,lon,estEntree,estSortie
 */

$gtfsDir = 'documentation/IDFM-gtfs/csv/';
$sortie = 'documentation/scripts/donnees-extraites/acces_entrees.csv';

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

echo "Chargement des libelles/numeros officiels (acces.csv)...\n";
$infosParAccId = [];
foreach (lireCsv($gtfsDir . 'acces.csv', ';') as $row) {
    $infosParAccId[$row['AccId']] = [
        'label' => $row['AccName'],
        'numero' => '' !== $row['AccShortName'] ? $row['AccShortName'] : '',
        'estEntree' => $row['AccIsEntry'],
        'estSortie' => $row['AccIsExit'],
    ];
}
echo count($infosParAccId) . " acces dans acces.csv.\n";

$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['accId', 'zdc', 'label', 'numero', 'lat', 'lon', 'estEntree', 'estSortie']);

$nb = 0;
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    if ('2' !== $row['location_type']) {
        continue;
    }

    $accId = str_replace('IDFM:StopPlaceEntrance:', '', $row['stop_id']);
    $zdc = str_replace('IDFM:', '', $row['parent_station']);
    $label = $infosParAccId[$accId]['label'] ?? $row['stop_name'];
    $numero = $infosParAccId[$accId]['numero'] ?? $row['stop_code'];
    $estEntree = $infosParAccId[$accId]['estEntree'] ?? '';
    $estSortie = $infosParAccId[$accId]['estSortie'] ?? '';

    fputcsv($fichier, [$accId, $zdc, $label, $numero, $row['stop_lat'], $row['stop_lon'], $estEntree, $estSortie]);
    ++$nb;
}
fclose($fichier);

echo "$nb acces ecrits dans $sortie.\n";
