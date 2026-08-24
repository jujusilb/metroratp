<?php

/**
 * Recupere les ZdC presents dans le referentiel officiel zones-d-arrets.csv mais absents du GTFS
 * actuel (donc de zdc_coordonnees.csv, voir extraire_coordonnees_zdc.php) - verifie le 2026-08-24 :
 * 1789 ZdC distincts sur 15539 (11,5%), totalement absents de stops.txt (pas juste sous un autre
 * location_type) - ils n'ont simplement plus de service GTFS programme actuellement, mais restent
 * de vrais arrets officiels avec un nom et une position.
 *
 * zones-d-arrets.csv donne leurs coordonnees en Lambert-93 (ZdAXEpsg2154/ZdAYEpsg2154, projection
 * francaise EPSG:2154) plutot qu'en WGS84 (GPS standard) - reprojetees ici via la formule officielle
 * IGN (Lambert conforme conique, ellipsoide GRS80). Formule validee ci-dessous contre des ZdC deja
 * connus des deux facons (comparaison directe avec leurs vraies coordonnees WGS84 de stops.txt).
 *
 * Une ZdC peut regrouper plusieurs ZdA (arrets physiques) : coordonnee retenue = moyenne des ZdA de
 * cette ZdC, nom retenu = celui du premier ZdA (le plus souvent identique/tres proche entre eux).
 *
 * Sortie : documentation/scripts/donnees-extraites/zdc_manquants_lambert93.csv
 * Colonnes : zdc,label,commune,latitude,longitude
 */

// --- Conversion Lambert-93 (EPSG:2154) -> WGS84, parametres officiels IGN ---
const LAMBERT93_N = 0.7256077650;
const LAMBERT93_C = 11754255.426;
const LAMBERT93_XS = 700000.000;
const LAMBERT93_YS = 12655612.050;
const LAMBERT93_LON0 = 3.0; // degres (meridien de reference)
const GRS80_E = 0.08181919112; // premiere excentricite

function lambert93VersWgs84(float $x, float $y): array
{
    $n = LAMBERT93_N;
    $c = LAMBERT93_C;
    $xs = LAMBERT93_XS;
    $ys = LAMBERT93_YS;
    $e = GRS80_E;
    $lon0 = deg2rad(LAMBERT93_LON0);

    $r = sqrt(($x - $xs) ** 2 + ($y - $ys) ** 2);
    $gamma = atan(($x - $xs) / ($ys - $y));
    $lon = $lon0 + $gamma / $n;

    $latIso = -log($r / $c) / $n;
    $lat = 2 * atan(exp($latIso)) - M_PI / 2;
    for ($i = 0; $i < 8; ++$i) {
        $lat = 2 * atan(
            ((1 + $e * sin($lat)) / (1 - $e * sin($lat))) ** ($e / 2)
            * exp($latIso)
        ) - M_PI / 2;
    }

    return [rad2deg($lat), rad2deg($lon)];
}

function lireCsv(string $chemin, string $sep = ','): \Generator
{
    $f = fopen($chemin, 'r');
    $header = fgetcsv($f, 0, $sep);
    $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
    $idx = array_flip($header);
    while (($row = fgetcsv($f, 0, $sep)) !== false) {
        $assoc = [];
        foreach ($idx as $col => $i) {
            $assoc[$col] = $row[$i] ?? '';
        }
        yield $assoc;
    }
    fclose($f);
}

$gtfsDir = 'documentation/IDFM-gtfs/csv/';

// --- Validation : comparer contre des ZdC deja connus (stops.txt) ---
echo "Validation de la reprojection contre des ZdC deja connus...\n";
$zdcWgs84Connus = [];
foreach (lireCsv('documentation/IDFM-gtfs/csv/stops.txt') as $row) {
    if ('1' === $row['location_type']) {
        $zdcWgs84Connus[str_replace('IDFM:', '', $row['stop_id'])] = [(float) $row['stop_lat'], (float) $row['stop_lon']];
    }
}

$nbTestes = 0;
$ecartMax = 0;
$ecartTotal = 0;
foreach (lireCsv($gtfsDir . 'zones-d-arrets.csv', ';') as $row) {
    $zdc = $row['ZdCId'];
    if (!isset($zdcWgs84Connus[$zdc]) || '' === $row['ZdAXEpsg2154'] || '' === $row['ZdAYEpsg2154']) {
        continue;
    }
    if ($nbTestes >= 30) {
        break;
    }
    [$lat, $lon] = lambert93VersWgs84((float) $row['ZdAXEpsg2154'], (float) $row['ZdAYEpsg2154']);
    [$latConnu, $lonConnu] = $zdcWgs84Connus[$zdc];
    $dLat = ($lat - $latConnu) * 111320;
    $dLon = ($lon - $lonConnu) * 111320 * cos(deg2rad($latConnu));
    $ecart = sqrt($dLat ** 2 + $dLon ** 2);
    $ecartMax = max($ecartMax, $ecart);
    $ecartTotal += $ecart;
    ++$nbTestes;
}
echo "$nbTestes ZdC testes. Ecart moyen : ".round($ecartTotal / max(1, $nbTestes))." m. Ecart max : ".round($ecartMax)." m.\n";
if ($ecartMax > 200) {
    echo "ATTENTION : ecart trop important, formule de reprojection probablement fausse - ARRET.\n";
    exit(1);
}
echo "Reprojection validee (ecarts coherents avec un simple decalage ZdA vs ZdC, pas une erreur de formule).\n\n";

// --- Extraction des ZdC manquants ---
$zdcConnus = [];
foreach (lireCsv('documentation/scripts/donnees-extraites/zdc_coordonnees.csv') as $row) {
    $zdcConnus[$row['zdc']] = true;
}
echo count($zdcConnus)." ZdC deja connus (zdc_coordonnees.csv).\n";

$parZdc = []; // zdc => ['label' => ..., 'commune' => ..., 'xs' => [], 'ys' => []]
foreach (lireCsv($gtfsDir . 'zones-d-arrets.csv', ';') as $row) {
    $zdc = $row['ZdCId'];
    if ('' === $zdc || isset($zdcConnus[$zdc])) {
        continue;
    }
    if ('' === $row['ZdAXEpsg2154'] || '' === $row['ZdAYEpsg2154']) {
        continue;
    }
    if (!isset($parZdc[$zdc])) {
        $parZdc[$zdc] = [
            'label' => $row['ZdAName'],
            'commune' => $row['ZdATown'],
            'xs' => [],
            'ys' => [],
        ];
    }
    $parZdc[$zdc]['xs'][] = (float) $row['ZdAXEpsg2154'];
    $parZdc[$zdc]['ys'][] = (float) $row['ZdAYEpsg2154'];
}
echo count($parZdc)." ZdC manquants avec au moins une coordonnee Lambert-93 exploitable.\n";

$sortie = 'documentation/scripts/donnees-extraites/zdc_manquants_lambert93.csv';
$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['zdc', 'label', 'commune', 'latitude', 'longitude']);
foreach ($parZdc as $zdc => $info) {
    $xMoyen = array_sum($info['xs']) / count($info['xs']);
    $yMoyen = array_sum($info['ys']) / count($info['ys']);
    [$lat, $lon] = lambert93VersWgs84($xMoyen, $yMoyen);
    fputcsv($fichier, [$zdc, $info['label'], $info['commune'], round($lat, 8), round($lon, 8)]);
}
fclose($fichier);

echo "Ecrit dans $sortie (".count($parZdc)." lignes).\n";
