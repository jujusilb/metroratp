<?php

/**
 * Meme principe que extraire_troncons_rer.py (union des paires de stations consecutives +
 * reduction geometrique pour retirer les raccourcis d'express/semi-directs), applique aux 8
 * lignes Transilien encore sans aucune topologie (H, J, K, L, N, P, R, U - 252 Desserte isolees
 * au total, decouvert le 2026-08-21 apres la completion du reseau bus/RER/telepherique/funiculaire).
 *
 * Version PHP autonome (contrairement au script RER original en Python, qui depend d'un
 * referentiel externe hors de ce depot pour les coordonnees Lambert-93) : utilise directement
 * stops.txt (stop_lat/stop_lon, WGS84) + haversine, comme tous les autres scripts d'extraction
 * bus/telepherique/funiculaire de cette session.
 *
 * Sortie : documentation/scripts/donnees-extraites/troncons_transilien.csv
 * Colonnes : route_label,zdc_a,zdc_b,nom_a,nom_b,duree_mediane_secondes,nb_observations
 */

const GTFS_DIR = 'documentation/IDFM-gtfs/csv/';
const SORTIE = 'documentation/scripts/donnees-extraites/troncons_transilien.csv';
const DUREE_MAX_SECONDES = 1800;
const MARGE_RACCOURCI = 1.25;

const ROUTES = [
    'C01737' => 'H',
    'C01739' => 'J',
    'C01738' => 'K',
    'C01740' => 'L',
    'C01736' => 'N',
    'C01730' => 'P',
    'C01731' => 'R',
    'C01741' => 'U',
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

function toSecondes(string $hms): ?int
{
    $parts = explode(':', $hms);
    if (3 !== \count($parts)) {
        return null;
    }

    return ((int) $parts[0]) * 3600 + ((int) $parts[1]) * 60 + (int) $parts[2];
}

function haversine(float $latA, float $lonA, float $latB, float $lonB): float
{
    $rayon = 6371000.0;
    $phi1 = deg2rad($latA);
    $phi2 = deg2rad($latB);
    $dPhi = deg2rad($latB - $latA);
    $dLambda = deg2rad($lonB - $lonA);
    $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

    return $rayon * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$coords = [];
$noms = [];
foreach (lireCsv(GTFS_DIR.'stops.txt') as $row) {
    if ('1' === $row['location_type']) {
        $zdcId = str_replace('IDFM:', '', $row['stop_id']);
        $coords[$zdcId] = [(float) $row['stop_lat'], (float) $row['stop_lon']];
        $noms[$zdcId] = $row['stop_name'];
    }
}
echo count($coords)." ZdC avec coordonnees.\n";

function distance(array $coords, string $a, string $b): ?float
{
    if (!isset($coords[$a], $coords[$b])) {
        return null;
    }

    return haversine($coords[$a][0], $coords[$a][1], $coords[$b][0], $coords[$b][1]);
}

$stopVersZdc = [];
foreach (lireCsv(GTFS_DIR.'stops.txt') as $row) {
    $parent = $row['parent_station'];
    if (str_starts_with($parent, 'IDFM:')) {
        $zdcId = substr($parent, 5);
        if (isset($coords[$zdcId])) {
            $stopVersZdc[$row['stop_id']] = $zdcId;
        }
    }
}

$codesRecherches = array_flip(array_map(static fn ($c) => 'IDFM:'.$c, array_keys(ROUTES)));
$tripVersLabel = [];
foreach (lireCsv(GTFS_DIR.'trips.txt') as $row) {
    if (isset($codesRecherches[$row['route_id']])) {
        $tripVersLabel[$row['trip_id']] = ROUTES[substr($row['route_id'], 5)];
    }
}
echo count($tripVersLabel)." trips trouves.\n";

$arretsParTrip = [];
foreach (lireCsv(GTFS_DIR.'stop_times.txt') as $row) {
    $tid = $row['trip_id'];
    if (!isset($tripVersLabel[$tid])) {
        continue;
    }
    $zdc = $stopVersZdc[$row['stop_id']] ?? null;
    if (null === $zdc) {
        continue;
    }
    $arretsParTrip[$tid][] = [(int) $row['stop_sequence'], $zdc, $row['arrival_time'], $row['departure_time']];
}
echo count($arretsParTrip)." trips avec arrets exploitables.\n";

$troncons = [];
foreach ($arretsParTrip as $tid => $arrets) {
    usort($arrets, static fn ($a, $b) => $a[0] <=> $b[0]);
    $label = $tripVersLabel[$tid];
    for ($i = 0; $i < count($arrets) - 1; ++$i) {
        [, $zdcA, , $depA] = $arrets[$i];
        [, $zdcB, $arrB] = $arrets[$i + 1];
        if ($zdcA === $zdcB) {
            continue;
        }
        $paire = [$zdcA, $zdcB];
        sort($paire);
        $cle = $label.'|'.$paire[0].'|'.$paire[1];
        $depSec = toSecondes($depA);
        $arrSec = toSecondes($arrB);
        $duree = null !== $depSec && null !== $arrSec ? $arrSec - $depSec : null;
        if (!isset($troncons[$cle])) {
            $troncons[$cle] = [];
        }
        if (null !== $duree && $duree > 0 && $duree < DUREE_MAX_SECONDES) {
            $troncons[$cle][] = $duree;
        }
    }
}
echo count($troncons)." aretes brutes (avant reduction).\n";

function plusCourtCheminConfirme(array $adj, array $poids, string $a, string $b): ?float
{
    $distances = [$a => 0.0];
    $aTraiter = [[0.0, $a]];
    while ([] !== $aTraiter) {
        usort($aTraiter, static fn ($x, $y) => $x[0] <=> $y[0]);
        [$d, $courant] = array_shift($aTraiter);
        $courant = (string) $courant;
        if ($courant === $b) {
            return $d;
        }
        if ($d > ($distances[$courant] ?? INF)) {
            continue;
        }
        foreach (array_keys($adj[$courant] ?? []) as $voisin) {
            $voisin = (string) $voisin;
            $p = $poids[$courant.'|'.$voisin] ?? null;
            if (null === $p) {
                continue;
            }
            $nd = $d + $p;
            if ($nd < ($distances[$voisin] ?? INF)) {
                $distances[$voisin] = $nd;
                $aTraiter[] = [$nd, $voisin];
            }
        }
    }

    return null;
}

$aretesParLigne = [];
foreach (array_keys($troncons) as $cle) {
    [$label, $a, $b] = explode('|', $cle);
    $aretesParLigne[$label][] = [$a, $b];
}

$tronconsRetenus = [];
foreach ($aretesParLigne as $label => $aretes) {
    $ordre = $aretes;
    usort($ordre, static function ($x, $y) use ($coords) {
        [$xa, $xb] = $x;
        [$ya, $yb] = $y;

        return (distance($coords, $ya, $yb) ?? 0) <=> (distance($coords, $xa, $xb) ?? 0);
    });

    $adjConfirme = [];
    $poidsConfirme = [];
    foreach ($ordre as [$a, $b]) {
        $distDirecte = distance($coords, $a, $b);
        $alt = null !== $distDirecte ? plusCourtCheminConfirme($adjConfirme, $poidsConfirme, $a, $b) : null;
        if (null !== $alt && $alt <= $distDirecte * MARGE_RACCOURCI) {
            continue;
        }
        $tronconsRetenus[$label.'|'.$a.'|'.$b] = $troncons[$label.'|'.$a.'|'.$b];
        $d = $distDirecte ?? 0.0;
        $adjConfirme[$a][$b] = true;
        $adjConfirme[$b][$a] = true;
        $poidsConfirme[$a.'|'.$b] = $d;
        $poidsConfirme[$b.'|'.$a] = $d;
    }
}
echo count($tronconsRetenus)." troncons retenus apres reduction.\n";

$fichier = fopen(SORTIE, 'w');
fputcsv($fichier, ['route_label', 'zdc_a', 'zdc_b', 'nom_a', 'nom_b', 'duree_mediane_secondes', 'nb_observations']);
ksort($tronconsRetenus);
foreach ($tronconsRetenus as $cle => $durees) {
    [$label, $a, $b] = explode('|', $cle);
    $mediane = '';
    if ([] !== $durees) {
        sort($durees);
        $n = count($durees);
        $mediane = 1 === $n % 2 ? $durees[(int) floor($n / 2)] : (int) round(($durees[$n / 2 - 1] + $durees[$n / 2]) / 2);
    }
    fputcsv($fichier, [$label, $a, $b, $noms[$a] ?? '', $noms[$b] ?? '', $mediane, count($durees)]);
}
fclose($fichier);
echo "\nEcrit dans ".SORTIE."\n";

$parLigne = [];
foreach (array_keys($tronconsRetenus) as $cle) {
    [$label] = explode('|', $cle);
    $parLigne[$label] = ($parLigne[$label] ?? 0) + 1;
}
foreach (array_unique(array_values(ROUTES)) as $label) {
    echo "  $label: ".($parLigne[$label] ?? 0)." troncons retenus\n";
}
