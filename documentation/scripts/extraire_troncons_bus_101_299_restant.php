<?php

/**
 * Meme principe que extraire_troncons_bus_autres_operateurs.php, pour les lignes non-RATP de la
 * plage 101-299 restantes (voir documentation/TODO.md) : ATM Croix du Sud (179/189-191/194-195/
 * 289-290), Keolis Grand Paris Vallee de la Marne (206-207/209/211-213/220), et la ligne 282
 * (operateur renomme "Keolis Grand Paris Seine Orly" dans le referentiel actuel — TODO.md la
 * notait encore sous son ancien nom "Keolis Ouest Val-de-Marne").
 *
 * La ligne 262 (Keolis Argenteuil) de la note originale n'existe plus sous ce numero dans le
 * referentiel-des-lignes.csv actuel (reseau Keolis Argenteuil integralement renumerote en serie
 * "64xx" depuis) : PAS importee, pas de correspondance fiable trouvee plutot que deviner.
 *
 * Cle par codeExterne (route_id IDFM), comme le script "autres operateurs" existant.
 *
 * Algorithme de reduction geometrique corrige (voir extraire_troncons_rer_c.php pour le detail du
 * bug trouve/corrige cette session) : traite les aretes les plus COURTES en premier contre un
 * graphe deja CONFIRME, avec cast (string) explicite a chaque lecture de cle de tableau (PHP
 * convertit silencieusement les cles numeriques en int, ce qui casse la comparaison stricte "===
 * destination" si on ne recast pas).
 *
 * Sortie : documentation/scripts/donnees-extraites/troncons_bus_101_299_restant.csv
 * Colonnes : code_externe,zdc_a,zdc_b,nom_a,nom_b,duree_mediane_secondes,nb_observations
 */

const GTFS_DIR = 'documentation/IDFM-gtfs/csv/';
const SORTIE = 'documentation/scripts/donnees-extraites/troncons_bus_101_299_restant.csv';
const DUREE_MAX_SECONDES = 1800;
const MARGE_RACCOURCI = 1.25;

// label affiche => codeExterne, pour la lisibilite des logs uniquement.
const LIGNES = [
    '179 (ATM Croix du Sud)' => 'C01200',
    '189 (ATM Croix du Sud)' => 'C01210',
    '190 (ATM Croix du Sud)' => 'C01211',
    '191 (ATM Croix du Sud)' => 'C01212',
    '194 (ATM Croix du Sud)' => 'C01214',
    '195 (ATM Croix du Sud)' => 'C01215',
    '289 (ATM Croix du Sud)' => 'C01264',
    '290 (ATM Croix du Sud)' => 'C01265',
    '206 (Keolis Grand Paris Vallee de la Marne)' => 'C01221',
    '207 (Keolis Grand Paris Vallee de la Marne)' => 'C01222',
    '209 (Keolis Grand Paris Vallee de la Marne)' => 'C02437',
    '211 (Keolis Grand Paris Vallee de la Marne)' => 'C01225',
    '212 (Keolis Grand Paris Vallee de la Marne)' => 'C01226',
    '213 (Keolis Grand Paris Vallee de la Marne)' => 'C01227',
    '220 (Keolis Grand Paris Vallee de la Marne)' => 'C01232',
    '282 (Keolis Grand Paris Seine Orly, ex Keolis Ouest Val-de-Marne)' => 'C00007',
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

$codes = array_values(LIGNES);
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
    echo 'ATTENTION, aucun trip trouve pour : '.implode(', ', $codesManquants)."\n";
}

$troncons = [];
$arretsParTrip = [];
foreach (lireCsv(GTFS_DIR.'stop_times.txt') as $row) {
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
foreach ($aretesParCode as $code => $aretes) {
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
