<?php

/**
 * Meme algorithme que extraire_troncons_bus_100_200.php, pour les lignes RATP (+ filiales
 * "RATP Cap ...") numerotees 201 a 299. Exclut volontairement les lignes non-RATP de cette plage
 * (Keolis Grand Paris Vallee de la Marne, Keolis Argenteuil, Keolis Ouest Val-de-Marne, ATM Croix
 * du Sud) — voir requete SQL prealable, gestionnaire.label LIKE 'RATP%'.
 *
 * Ecrit en ajout dans documentation/scripts/donnees-extraites/troncons_bus.csv.
 */

$gtfsDir = 'documentation/IDFM-gtfs/';
$sortie = 'documentation/scripts/donnees-extraites/troncons_bus.csv';

$routes = [
    '201' => 'C01219', '202' => 'C02714', '203' => 'C01220', '208' => 'C01223', '210' => 'C01224',
    '214' => 'C01228', '215' => 'C01229', '216' => 'C01230', '217' => 'C01231', '221' => 'C01233',
    '234' => 'C01234', '235' => 'C01235', '237' => 'C01236', '238' => 'C01237', '239' => 'C01238',
    '240' => 'C02834', '241' => 'C01239', '244' => 'C01240', '245' => 'C02713', '247' => 'C01695',
    '248' => 'C01696', '249' => 'C01241', '250' => 'C01242', '251' => 'C01243', '252' => 'C01244',
    '253' => 'C01245', '254' => 'C01808', '255' => 'C01246', '256' => 'C01247', '258' => 'C01248',
    '259' => 'C02000', '260' => 'C02027', '261' => 'C01249', '263' => 'C02314', '268' => 'C01251',
    '269' => 'C01252', '270' => 'C01253', '272' => 'C01254', '274' => 'C01255', '275' => 'C01256',
    '276' => 'C01257', '277' => 'C02744', '278' => 'C01258', '281' => 'C01260', '285' => 'C01262',
    '286' => 'C01263', '292' => 'C01267', '294' => 'C01268', '297' => 'C01270', '299' => 'C01271',
];

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

echo "Chargement des coordonnees ZdC (stops.txt, location_type=1)...\n";
$coords = [];
$nomsZdc = [];
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    if ('1' === $row['location_type']) {
        $zdcId = str_replace('IDFM:', '', $row['stop_id']);
        $coords[$zdcId] = [(float) $row['stop_lon'], (float) $row['stop_lat']];
        $nomsZdc[$zdcId] = $row['stop_name'];
    }
}
echo \count($coords) . " ZdC avec coordonnees.\n";

echo "Association stop_id -> ZdC (parent_station)...\n";
$stopVersZdc = [];
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    $parent = $row['parent_station'];
    if (str_starts_with($parent, 'IDFM:')) {
        $zdcId = substr($parent, 5);
        if (isset($coords[$zdcId])) {
            $stopVersZdc[$row['stop_id']] = $zdcId;
        }
    }
}

echo "Chargement des trips pour les routes cibles...\n";
$routesGtfs = array_flip(array_map(static fn ($c) => 'IDFM:' . $c, $routes));
$tripVersRoute = [];
foreach (lireCsv($gtfsDir . 'trips.txt') as $row) {
    if (isset($routesGtfs[$row['route_id']])) {
        $tripVersRoute[$row['trip_id']] = $routesGtfs[$row['route_id']];
    }
}
echo \count($tripVersRoute) . " trips trouves (sur " . \count($routes) . " lignes cherchees).\n";
$labelsVus = array_unique(array_values($tripVersRoute));
$labelsManquants = array_diff(array_keys($routes), $labelsVus);
if ([] !== $labelsManquants) {
    echo "ATTENTION, aucun trip trouve pour les lignes : " . implode(', ', $labelsManquants) . "\n";
}

echo "Lecture de stop_times.txt (fichier volumineux, patience)...\n";
$arretsParTrip = [];
foreach (lireCsv($gtfsDir . 'stop_times.txt') as $row) {
    $tid = $row['trip_id'];
    if (!isset($tripVersRoute[$tid])) {
        continue;
    }
    $zdc = $stopVersZdc[$row['stop_id']] ?? null;
    if (null === $zdc) {
        continue;
    }
    $arretsParTrip[$tid][] = [
        (int) $row['stop_sequence'],
        $zdc,
        $row['arrival_time'],
        $row['departure_time'],
    ];
}
echo \count($arretsParTrip) . " trips avec arrets exploitables.\n";

function toSecondes(string $hms): ?int
{
    $parts = explode(':', $hms);
    if (3 !== \count($parts)) {
        return null;
    }

    return ((int) $parts[0]) * 3600 + ((int) $parts[1]) * 60 + (int) $parts[2];
}

$troncons = [];
foreach ($arretsParTrip as $tid => $arrets) {
    usort($arrets, static fn ($a, $b) => $a[0] <=> $b[0]);
    $route = $tripVersRoute[$tid];
    for ($i = 0; $i < \count($arrets) - 1; ++$i) {
        [, $zdcA, , $depA] = $arrets[$i];
        [, $zdcB, $arrB, ] = $arrets[$i + 1];
        if ($zdcA === $zdcB) {
            continue;
        }
        $paire = [$zdcA, $zdcB];
        sort($paire);
        $cle = $route . '|' . $paire[0] . '|' . $paire[1];
        $depSec = toSecondes($depA);
        $arrSec = toSecondes($arrB);
        if (null !== $depSec && null !== $arrSec) {
            $duree = $arrSec - $depSec;
            if ($duree > 0 && $duree < 1800) {
                $troncons[$cle][] = $duree;
            } elseif (!isset($troncons[$cle])) {
                $troncons[$cle] = [];
            }
        } elseif (!isset($troncons[$cle])) {
            $troncons[$cle] = [];
        }
    }
}
echo \count($troncons) . " aretes brutes (avant reduction).\n";

function distance(array $coords, string $a, string $b): ?float
{
    if (!isset($coords[$a], $coords[$b])) {
        return null;
    }
    [$lonA, $latA] = $coords[$a];
    [$lonB, $latB] = $coords[$b];
    $dLat = ($latB - $latA) * 111320;
    $dLon = ($lonB - $lonA) * 111320 * cos(deg2rad(($latA + $latB) / 2));

    return sqrt($dLat ** 2 + $dLon ** 2);
}

function plusCourtCheminSansAreteDirecte(array $adj, array $coords, string $a, string $b): ?float
{
    $distances = [$a => 0.0];
    $aTraiter = [[0.0, $a]];
    while ([] !== $aTraiter) {
        usort($aTraiter, static fn ($x, $y) => $x[0] <=> $y[0]);
        [$d, $courant] = array_shift($aTraiter);
        if ($courant === $b) {
            return $d;
        }
        if ($d > ($distances[$courant] ?? INF)) {
            continue;
        }
        foreach ($adj[$courant] ?? [] as $voisin) {
            if ($courant === $a && $voisin === $b) {
                continue;
            }
            $p = distance($coords, $courant, $voisin);
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

$aretesParRoute = [];
foreach (array_keys($troncons) as $cle) {
    [$route, $a, $b] = explode('|', $cle);
    $aretesParRoute[$route][$a . '|' . $b] = [$a, $b];
}

$tronconsRetenus = [];
foreach ($aretesParRoute as $route => $aretes) {
    $adj = [];
    foreach ($aretes as [$a, $b]) {
        $adj[$a][$b] = true;
        $adj[$b][$a] = true;
    }
    foreach ($adj as $noeud => &$voisins) {
        $voisins = array_keys($voisins);
    }
    unset($voisins);

    $ordre = array_keys($aretes);
    usort($ordre, static function ($x, $y) use ($aretes, $coords) {
        [$ax, $bx] = $aretes[$x];
        [$ay, $by] = $aretes[$y];

        return (distance($coords, $ay, $by) ?? 0) <=> (distance($coords, $ax, $bx) ?? 0);
    });

    foreach ($ordre as $cleArete) {
        [$a, $b] = $aretes[$cleArete];
        $distDirecte = distance($coords, $a, $b);
        $alt = null !== $distDirecte ? plusCourtCheminSansAreteDirecte($adj, $coords, $a, $b) : null;
        if (null !== $alt && $alt <= $distDirecte * 1.25) {
            $adj[$a] = array_values(array_diff($adj[$a], [$b]));
            $adj[$b] = array_values(array_diff($adj[$b], [$a]));
        } else {
            $tronconsRetenus[$route . '|' . $a . '|' . $b] = $troncons[$route . '|' . $a . '|' . $b];
        }
    }
}
echo \count($tronconsRetenus) . " troncons retenus apres reduction.\n";

$fichier = fopen($sortie, 'a');
foreach ($tronconsRetenus as $cle => $durees) {
    [$route, $a, $b] = explode('|', $cle);
    $mediane = '';
    if ([] !== $durees) {
        sort($durees);
        $n = \count($durees);
        $mediane = 1 === $n % 2 ? $durees[(int) floor($n / 2)] : (int) round(($durees[$n / 2 - 1] + $durees[$n / 2]) / 2);
    }
    fputcsv($fichier, [$route, $a, $b, $nomsZdc[$a] ?? '', $nomsZdc[$b] ?? '', $mediane, \count($durees)]);
}
fclose($fichier);

echo "Ecrit dans $sortie.\n";
$total = 0;
foreach ($routes as $label => $code) {
    $nb = 0;
    foreach (array_keys($tronconsRetenus) as $cle) {
        if (str_starts_with($cle, $label . '|')) {
            ++$nb;
        }
    }
    $total += $nb;
    echo "  $label ($code) : $nb troncons\n";
}
echo "Total : $total troncons sur " . \count($routes) . " lignes.\n";
