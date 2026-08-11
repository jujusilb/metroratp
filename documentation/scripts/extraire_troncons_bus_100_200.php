<?php

/**
 * Meme algorithme que extraire_troncons_bus.py, pour les lignes RATP (RATP + filiales "RATP Cap
 * ...") numerotees 101 a 199 (voir requete SQL prealable : gestionnaire.label LIKE 'RATP%',
 * type_transport = Bus, plage 100-200). Contrairement a la plage 20-100, aucune ambiguite de
 * numero ici (chaque label n'a qu'une seule Ligne RATP dans cette plage) : cle par route_label
 * comme le script d'origine.
 *
 * Ecrit en ajout dans documentation/scripts/donnees-extraites/troncons_bus.csv.
 */

$gtfsDir = 'documentation/IDFM-gtfs/';
$sortie = 'documentation/scripts/donnees-extraites/troncons_bus.csv';

$routes = [
    '101' => 'C01130', '102' => 'C01131', '103' => 'C01132', '104' => 'C01133', '105' => 'C01134',
    '106' => 'C01135', '107' => 'C01136', '108' => 'C01137', '109' => 'C01138', '110' => 'C01139',
    '111' => 'C01140', '112' => 'C01141', '113' => 'C01142', '114' => 'C01143', '115' => 'C01144',
    '116' => 'C01145', '117' => 'C01146', '118' => 'C01147', '119' => 'C01148', '120' => 'C01149',
    '121' => 'C01150', '122' => 'C01151', '123' => 'C01152', '124' => 'C01153', '125' => 'C01154',
    '126' => 'C01155', '127' => 'C01156', '128' => 'C01157', '129' => 'C01158', '131' => 'C01159',
    '132' => 'C01160', '133' => 'C01161', '137' => 'C01163', '138' => 'C01164', '139' => 'C01165',
    '140' => 'C01166', '141' => 'C01167', '143' => 'C01168', '144' => 'C01169', '145' => 'C01170',
    '146' => 'C01171', '147' => 'C01172', '148' => 'C01173', '150' => 'C01174', '151' => 'C01175',
    '152' => 'C01176', '153' => 'C01177', '157' => 'C01180', '158' => 'C01181', '159' => 'C01182',
    '160' => 'C01183', '162' => 'C01184', '163' => 'C01185', '164' => 'C01186', '165' => 'C01187',
    '166' => 'C01188', '167' => 'C01189', '168' => 'C02007', '169' => 'C01190', '170' => 'C01191',
    '171' => 'C01192', '172' => 'C01193', '173' => 'C01194', '174' => 'C01195', '175' => 'C01196',
    '176' => 'C01197', '177' => 'C01198', '178' => 'C01199', '180' => 'C01201', '181' => 'C01202',
    '182' => 'C01203', '183' => 'C01204', '184' => 'C01205', '185' => 'C01206', '186' => 'C01207',
    '187' => 'C01208', '188' => 'C01209', '192' => 'C01213', '193' => 'C02288', '196' => 'C01216',
    '197' => 'C01217', '199' => 'C01218',
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
