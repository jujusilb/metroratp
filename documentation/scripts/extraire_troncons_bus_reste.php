<?php

/**
 * Meme principe que extraire_troncons_bus_101_299_restant.php (reduction geometrique du graphe
 * GTFS, plus courtes aretes d'abord contre un graphe deja confirme), generalise a TOUTES les
 * lignes de bus/car restantes plutot qu'une liste tapee a la main : la liste des codeExterne a
 * traiter est lue directement dans la base locale (toute Ligne de type Bus/Car, avec codeExterne,
 * dont AUCUNE Desserte n'a encore de Troncon) - decouvert le 2026-08-20 en croisant le nombre de
 * Desserte (31787) et de Troncon (7760) : 1167 lignes de bus entierement sans topologie (24270
 * Desserte isolees, ~78% de toutes les Desserte de bus). Reprend l'algorithme tel quel : rien de
 * manuel a verifier ligne par ligne, seule la LISTE des lignes a traiter etait auparavant limitee
 * a la main (d'ou le "trop volumineux pour un seul passage" note dans TODO.md - la lecture
 * programmatique de la liste leve cette limite).
 *
 * Sortie : documentation/scripts/donnees-extraites/troncons_bus_reste.csv
 * Colonnes : code_externe,zdc_a,zdc_b,nom_a,nom_b,duree_mediane_secondes,nb_observations
 */

const GTFS_DIR = 'documentation/IDFM-gtfs/csv/';
const SORTIE = 'documentation/scripts/donnees-extraites/troncons_bus_reste.csv';
const DUREE_MAX_SECONDES = 1800;
const MARGE_RACCOURCI = 1.25;

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=metroratp;charset=utf8mb4', 'root', '');
$sql = "SELECT DISTINCT l.code_externe
        FROM ligne l
        JOIN type_transport tt ON tt.id = l.type_transport_id
        JOIN desserte d ON d.ligne_id = l.id
        WHERE tt.label IN ('Bus','Car') AND l.code_externe IS NOT NULL
        GROUP BY l.id
        HAVING SUM(CASE WHEN d.id IN (SELECT desserte_id FROM troncon_desserte) THEN 1 ELSE 0 END) = 0";
$codes = array_column($pdo->query($sql)->fetchAll(), 'code_externe');
echo count($codes)." lignes de bus/car sans aucun troncon, lues depuis la base.\n";

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

$codesRecherches = array_flip(array_map(static fn ($c) => 'IDFM:'.$c, $codes));
$tripVersCode = [];
foreach (lireCsv(GTFS_DIR.'trips.txt') as $row) {
    if (isset($codesRecherches[$row['route_id']])) {
        $tripVersCode[$row['trip_id']] = substr($row['route_id'], 5);
    }
}
echo count($tripVersCode)." trips trouves (sur ".count($codes)." lignes cherchees).\n";
$codesVus = array_unique(array_values($tripVersCode));
$codesManquants = array_diff($codes, $codesVus);
if ([] !== $codesManquants) {
    echo count($codesManquants)." lignes sans aucun trip trouve dans le GTFS actuel (referentiel change depuis l'import initial ?).\n";
}

$troncons = [];
$arretsParTrip = [];
$ligneNo = 0;
foreach (lireCsv(GTFS_DIR.'stop_times.txt') as $row) {
    if (0 === ++$ligneNo % 2000000) {
        echo "  ... $ligneNo lignes de stop_times.txt lues\n";
    }
    $tid = $row['trip_id'];
    if (!isset($tripVersCode[$tid])) {
        continue;
    }
    $zdc = $stopVersZdc[$row['stop_id']] ?? null;
    if (null === $zdc) {
        continue;
    }
    $arretsParTrip[$tid][] = [(int) $row['stop_sequence'], $zdc, $row['arrival_time'], $row['departure_time']];
}
echo count($arretsParTrip)." trips avec arrets exploitables.\n";

foreach ($arretsParTrip as $tid => $arrets) {
    usort($arrets, static fn ($a, $b) => $a[0] <=> $b[0]);
    $code = $tripVersCode[$tid];
    for ($i = 0; $i < count($arrets) - 1; ++$i) {
        [, $zdcA, , $depA] = $arrets[$i];
        [, $zdcB, $arrB] = $arrets[$i + 1];
        if ($zdcA === $zdcB) {
            continue;
        }
        $paire = [$zdcA, $zdcB];
        sort($paire);
        $cle = $code.'|'.$paire[0].'|'.$paire[1];
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

// ---- reduction geometrique : plus court d'abord, contre un graphe deja confirme ----
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

$aretesParCode = [];
foreach (array_keys($troncons) as $cle) {
    [$code, $a, $b] = explode('|', $cle);
    $aretesParCode[$code][] = [$a, $b];
}

$tronconsRetenus = [];
$ligneTraitee = 0;
foreach ($aretesParCode as $code => $aretes) {
    if (0 === ++$ligneTraitee % 100) {
        echo "  ... reduction geometrique : $ligneTraitee / ".count($aretesParCode)." lignes\n";
    }
    $ordre = $aretes;
    usort($ordre, static function ($x, $y) use ($coords) {
        [$xa, $xb] = $x;
        [$ya, $yb] = $y;

        return (distance($coords, $xa, $xb) ?? 0) <=> (distance($coords, $ya, $yb) ?? 0);
    });

    $adjConfirme = [];
    $poidsConfirme = [];
    foreach ($ordre as [$a, $b]) {
        $distDirecte = distance($coords, $a, $b);
        $alt = null !== $distDirecte ? plusCourtCheminConfirme($adjConfirme, $poidsConfirme, $a, $b) : null;
        if (null !== $alt && $alt <= $distDirecte * MARGE_RACCOURCI) {
            continue;
        }
        $tronconsRetenus[$code.'|'.$a.'|'.$b] = $troncons[$code.'|'.$a.'|'.$b];
        $d = $distDirecte ?? 0.0;
        $adjConfirme[$a][$b] = true;
        $adjConfirme[$b][$a] = true;
        $poidsConfirme[$a.'|'.$b] = $d;
        $poidsConfirme[$b.'|'.$a] = $d;
    }
}
echo count($tronconsRetenus)." troncons retenus apres reduction.\n";

$fichier = fopen(SORTIE, 'w');
fputcsv($fichier, ['code_externe', 'zdc_a', 'zdc_b', 'nom_a', 'nom_b', 'duree_mediane_secondes', 'nb_observations']);
ksort($tronconsRetenus);
foreach ($tronconsRetenus as $cle => $durees) {
    [$code, $a, $b] = explode('|', $cle);
    $mediane = '';
    if ([] !== $durees) {
        sort($durees);
        $n = count($durees);
        $mediane = 1 === $n % 2 ? $durees[(int) floor($n / 2)] : (int) round(($durees[$n / 2 - 1] + $durees[$n / 2]) / 2);
    }
    fputcsv($fichier, [$code, $a, $b, $noms[$a] ?? '', $noms[$b] ?? '', $mediane, count($durees)]);
}
fclose($fichier);
echo "\nEcrit dans ".SORTIE."\n";
