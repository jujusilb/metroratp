<?php

/**
 * Meme algorithme que extraire_troncons_bus.py / extraire_troncons_bus_supplement.php, pour les
 * lignes numerotees 20-100 exploitees par d'autres reseaux que la RATP (Keolis Roissy, Keolis
 * Argenteuil, Transdev Boucle des Lys / Vallee du Loing / Nord Seine-Saint-Denis / Coteaux de la
 * Marne, Keolis Nord Val d'Oise) : ces reseaux reutilisent independamment des numeros deja pris
 * par une ligne RATP (ex: leur "34" n'a rien a voir avec un eventuel bus RATP 34).
 *
 * Cle par codeExterne (route_id IDFM), PAS par numero affiche : plusieurs lignes ci-dessous
 * partagent le meme numero (34, 100) sans etre la meme ligne physique, contrairement aux lignes
 * RATP ou le numero est unique dans ce script.
 *
 * Sortie : documentation/scripts/donnees-extraites/troncons_bus_autres_operateurs.csv
 * Colonnes : code_externe,zdc_a,zdc_b,nom_a,nom_b,duree_mediane_secondes,nb_observations
 */

$gtfsDir = 'documentation/IDFM-gtfs/';
$sortie = 'documentation/scripts/donnees-extraites/troncons_bus_autres_operateurs.csv';

// label affiche => codeExterne, uniquement pour la lisibilite des logs (le code est la vraie cle).
$lignes = [
    '20 (Keolis Roissy)' => 'C01867',
    '20 (Keolis Argenteuil)' => 'C02264',
    '21 (Transdev Boucle des Lys)' => 'C00154',
    '22 (Keolis Roissy)' => 'C00609',
    '23 (Keolis Roissy)' => 'C00610',
    '24 (Keolis Roissy)' => 'C00612',
    '25 (Keolis Roissy)' => 'C00611',
    '25 (Keolis Argenteuil)' => 'C02731',
    '26 (Keolis Argenteuil)' => 'C02730',
    '27 (Keolis Roissy)' => 'C02059',
    '31 (Keolis Roissy)' => 'C00208',
    '32 (Keolis Roissy)' => 'C00209',
    '33 (Keolis Roissy)' => 'C00211',
    '34 (Keolis Argenteuil)' => 'C00306',
    '34 (Transdev Vallee du Loing)' => 'C00815',
    '34 (Keolis Roissy)' => 'C02164',
    '36 (Keolis Roissy)' => 'C00616',
    '37 (Keolis Roissy)' => 'C00617',
    '39 (Transdev Nord Seine-Saint-Denis)' => 'C00213',
    '43 (Transdev Nord Seine-Saint-Denis)' => 'C00214',
    '45 (Transdev Nord Seine-Saint-Denis)' => 'C00216',
    '100 (Transdev Coteaux de la Marne)' => 'C00263',
    '100 (Keolis Nord Val d\'Oise)' => 'C01675',
];
$codes = array_values($lignes);

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
$codesRecherches = array_flip(array_map(static fn ($c) => 'IDFM:' . $c, $codes));
$tripVersCode = [];
foreach (lireCsv($gtfsDir . 'trips.txt') as $row) {
    if (isset($codesRecherches[$row['route_id']])) {
        $tripVersCode[$row['trip_id']] = substr($row['route_id'], 5); // enleve "IDFM:"
    }
}
echo \count($tripVersCode) . " trips trouves (sur " . \count($codes) . " lignes cherchees).\n";
$codesVus = array_unique(array_values($tripVersCode));
$codesManquants = array_diff($codes, $codesVus);
if ([] !== $codesManquants) {
    echo "ATTENTION, aucun trip trouve pour : " . implode(', ', $codesManquants) . "\n";
}

echo "Lecture de stop_times.txt (fichier volumineux, patience)...\n";
$arretsParTrip = [];
foreach (lireCsv($gtfsDir . 'stop_times.txt') as $row) {
    $tid = $row['trip_id'];
    if (!isset($tripVersCode[$tid])) {
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

$troncons = []; // cle "code|a|b" (a<b) => durees[]
foreach ($arretsParTrip as $tid => $arrets) {
    usort($arrets, static fn ($a, $b) => $a[0] <=> $b[0]);
    $code = $tripVersCode[$tid];
    for ($i = 0; $i < \count($arrets) - 1; ++$i) {
        [, $zdcA, , $depA] = $arrets[$i];
        [, $zdcB, $arrB, ] = $arrets[$i + 1];
        if ($zdcA === $zdcB) {
            continue;
        }
        $paire = [$zdcA, $zdcB];
        sort($paire);
        $cle = $code . '|' . $paire[0] . '|' . $paire[1];
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

$aretesParCode = [];
foreach (array_keys($troncons) as $cle) {
    [$code, $a, $b] = explode('|', $cle);
    $aretesParCode[$code][$a . '|' . $b] = [$a, $b];
}

$tronconsRetenus = [];
foreach ($aretesParCode as $code => $aretes) {
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
            $tronconsRetenus[$code . '|' . $a . '|' . $b] = $troncons[$code . '|' . $a . '|' . $b];
        }
    }
}
echo \count($tronconsRetenus) . " troncons retenus apres reduction.\n";

$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['code_externe', 'zdc_a', 'zdc_b', 'nom_a', 'nom_b', 'duree_mediane_secondes', 'nb_observations']);
foreach ($tronconsRetenus as $cle => $durees) {
    [$code, $a, $b] = explode('|', $cle);
    $mediane = '';
    if ([] !== $durees) {
        sort($durees);
        $n = \count($durees);
        $mediane = 1 === $n % 2 ? $durees[(int) floor($n / 2)] : (int) round(($durees[$n / 2 - 1] + $durees[$n / 2]) / 2);
    }
    fputcsv($fichier, [$code, $a, $b, $nomsZdc[$a] ?? '', $nomsZdc[$b] ?? '', $mediane, \count($durees)]);
}
fclose($fichier);

echo "Ecrit dans $sortie.\n";
foreach ($lignes as $label => $code) {
    $nb = 0;
    foreach (array_keys($tronconsRetenus) as $cle) {
        if (str_starts_with($cle, $code . '|')) {
            ++$nb;
        }
    }
    echo "  $label ($code) : $nb troncons\n";
}
