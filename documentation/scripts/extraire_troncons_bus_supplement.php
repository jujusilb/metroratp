<?php

/**
 * Portage PHP de extraire_troncons_bus.py, pour les lignes RATP decouvertes apres coup
 * (agency_id GTFS different de l'agence "RATP" principale : "RATP Cap Boucle Nord de Seine",
 * agency_id IDFM:1090) : lignes 66, 74, 85. Meme algorithme exact (aretes consecutives par
 * trip, duree mediane, reduction des raccourcis via plus-court-chemin sans l'arete directe),
 * mais coordonnees prises directement dans stops.txt (location_type=1, stop_lat/stop_lon)
 * plutot que via le referentiel externe zones-de-correspondance.csv (absent sur cette machine).
 *
 * Ecrit en ajout dans documentation/scripts/donnees-extraites/troncons_bus.csv (ne touche pas
 * aux lignes deja presentes).
 */

$gtfsDir = 'documentation/IDFM-gtfs/';
$sortie = 'documentation/scripts/donnees-extraites/troncons_bus.csv';

$routes = [
    '66' => 'C01102',
    '74' => 'C01109',
    '85' => 'C01117',
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
echo \count($tripVersRoute) . " trips trouves.\n";

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

$troncons = []; // cle "route|a|b" (a<b) => durees[]
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
    // Distance planaire approximative (echelle Paris, suffisant pour comparer deux distances) :
    // conversion degres -> metres approx (1 deg lat ~ 111km, 1 deg lon ~ 111km*cos(lat)).
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

// Regroupe par route pour construire le graphe d'adjacence par ligne.
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

    // Traite les aretes les plus longues d'abord (comme le script Python).
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
    $mediane = [] !== $durees ? (int) round(array_sum($durees) / \count($durees)) : '';
    if ([] !== $durees) {
        sort($durees);
        $n = \count($durees);
        $mediane = 1 === $n % 2 ? $durees[(int) floor($n / 2)] : (int) round(($durees[$n / 2 - 1] + $durees[$n / 2]) / 2);
    }
    fputcsv($fichier, [$route, $a, $b, $nomsZdc[$a] ?? '', $nomsZdc[$b] ?? '', $mediane, \count($durees)]);
}
fclose($fichier);

echo "Ecrit dans $sortie.\n";
foreach ($routes as $label => $code) {
    $nb = 0;
    foreach ($tronconsRetenus as $cle => $_) {
        if (str_starts_with($cle, $label . '|')) {
            ++$nb;
        }
    }
    echo "  $label ($code) : $nb troncons\n";
}
