<?php

/**
 * Extrait le graphe physique (troncons = paires de stations adjacentes) de la ligne RER C
 * depuis le GTFS IDFM, meme methode que documentation/scripts/extraire_troncons_rer.py (deja
 * utilise pour A/B/D/E) : union des paires de stations consecutives observees dans tous les
 * passages, puis reduction geometrique pour retirer les "raccourcis" des missions
 * semi-directes/rapides qui sautent des gares (le nombre d'observations n'est pas un signal
 * fiable pour ca, voir le docblock du script python).
 *
 * Reecrit en PHP (au lieu d'etendre le script python existant) car son GTFS_DIR/REFERENTIEL_DIR
 * pointaient vers des chemins qui n'existent plus (le feed GTFS local a ete reorganise sous
 * documentation/IDFM-gtfs/csv/ depuis, et le referentiel externe utilise pour les coordonnees
 * Lambert93 n'existe plus sur cette machine). Utilise a la place les coordonnees WGS84 deja
 * extraites dans documentation/scripts/donnees-extraites/zdc_coordonnees.csv (distance
 * approximee par la formule de Haversine, suffisant a l'echelle de l'Ile-de-France pour detecter
 * des raccourcis).
 *
 * Sortie : documentation/scripts/donnees-extraites/troncons_rer_c.csv (memes colonnes que
 * troncons_rer.csv, fichier separe pour ne pas re-generer les lignes A/B/D/E deja verifiees).
 */

const ROUTE_ID = 'IDFM:C01727';
const GTFS_DIR = 'documentation/IDFM-gtfs/csv/';
const ZDC_COORDS_CSV = 'documentation/scripts/donnees-extraites/zdc_coordonnees.csv';
const SORTIE = 'documentation/scripts/donnees-extraites/troncons_rer_c.csv';
const DUREE_MAX_SECONDES = 1800;
const MARGE_RACCOURCI = 1.25;

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

function toSecondes(string $hms): int
{
    [$h, $m, $s] = explode(':', $hms);

    return ((int) $h * 3600) + ((int) $m * 60) + (int) $s;
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

// ---- Coordonnees ZdC (WGS84) ----
$coords = [];
foreach (lireCsv(ZDC_COORDS_CSV) as $row) {
    $coords[$row['zdc']] = [(float) $row['latitude'], (float) $row['longitude']];
}

function distance(array $coords, string $a, string $b): ?float
{
    if (!isset($coords[$a], $coords[$b])) {
        return null;
    }

    return haversine($coords[$a][0], $coords[$a][1], $coords[$b][0], $coords[$b][1]);
}

// ---- ZdCId + nom par stop_id (via parent_station, location_type=1 uniquement) ----
$zdcParStop = [];
$nomParZdc = [];
foreach (lireCsv(GTFS_DIR.'stops.txt') as $row) {
    $parent = $row['parent_station'];
    if (str_starts_with($parent, 'IDFM:')) {
        $zdcId = substr($parent, 5);
        $zdcParStop[$row['stop_id']] = $zdcId;
    }
}
foreach (lireCsv(GTFS_DIR.'stops.txt') as $row) {
    if ('1' === $row['location_type']) {
        $nomParZdc[substr($row['stop_id'], 5)] = $row['stop_name'];
    }
}

// ---- trip_id de la ligne C ----
$tripsInteressants = [];
foreach (lireCsv(GTFS_DIR.'trips.txt') as $row) {
    if (ROUTE_ID === $row['route_id']) {
        $tripsInteressants[$row['trip_id']] = true;
    }
}
echo count($tripsInteressants)." trips trouves pour ".ROUTE_ID."\n";

// ---- arrets par trip (ordonnes) ----
$arretsParTrip = [];
foreach (lireCsv(GTFS_DIR.'stop_times.txt') as $row) {
    $tid = $row['trip_id'];
    if (!isset($tripsInteressants[$tid])) {
        continue;
    }
    $zdc = $zdcParStop[$row['stop_id']] ?? null;
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

// ---- union des paires consecutives + durees observees ----
/** @var array<string, int[]> */
$troncons = [];
foreach ($arretsParTrip as $arrets) {
    usort($arrets, fn ($a, $b) => $a[0] <=> $b[0]);
    for ($i = 0; $i < count($arrets) - 1; ++$i) {
        [, $zdcA, , $depA] = $arrets[$i];
        [, $zdcB, $arrB] = $arrets[$i + 1];
        if ($zdcA === $zdcB) {
            continue;
        }
        $paire = [$zdcA, $zdcB];
        sort($paire);
        $cle = implode('|', $paire);
        if (!isset($troncons[$cle])) {
            $troncons[$cle] = [];
        }
        try {
            $duree = toSecondes($arrB) - toSecondes($depA);
        } catch (\Throwable) {
            $duree = null;
        }
        if (null !== $duree && $duree > 0 && $duree < DUREE_MAX_SECONDES) {
            $troncons[$cle][] = $duree;
        }
    }
}
echo count($troncons)." aretes brutes (avant reduction)\n";

// ---- reduction geometrique (retire les raccourcis d'express) ----
//
// Contrairement au script python original (A/B/D/E, "plus long d'abord, retire si un chemin
// alternatif existe deja"), la ligne C a plusieurs missions semi-directes qui se chevauchent sur
// le meme corridor (ex: Paris-Ivry-Vitry-Ardoines-Choisy) avec des sauts de longueurs differentes.
// Traiter les aretes les plus longues en premier peut alors se faire "tromper" par une autre arete
// raccourci deja presente (elle aussi pas encore retiree) qui sert de faux chemin alternatif court.
//
// Approche retenue a la place : traiter les aretes les plus COURTES en premier, en ne les comparant
// qu'a un graphe de reference deja CONFIRME (jamais a d'autres raccourcis pas encore juges). Une
// arete courte est presque toujours un vrai saut minimal entre stations adjacentes (un raccourci
// est par definition plus long qu'au moins un des vrais sauts qu'il saute) : elle est donc confirmee
// directement. Une arete plus longue n'est confirmee que si aucun chemin via le graphe DEJA
// confirme ne l'approche a moins de 25% pres (sinon c'est un raccourci qui saute des aretes reelles
// deja etablies).
$ordre = array_keys($troncons);
usort($ordre, function ($x, $y) use ($coords) {
    [$xa, $xb] = explode('|', $x);
    [$ya, $yb] = explode('|', $y);

    return (distance($coords, $xa, $xb) ?? 0) <=> (distance($coords, $ya, $yb) ?? 0);
});

function plusCourtCheminConfirme(array $adj, array $poids, string $a, string $b): ?float
{
    $distances = [$a => 0.0];
    $aTraiter = [[0.0, $a]];
    while ([] !== $aTraiter) {
        usort($aTraiter, fn ($x, $y) => $x[0] <=> $y[0]);
        [$d, $courant] = array_shift($aTraiter);
        // PHP convertit silencieusement les cles de tableau numeriques (ZdCId) en int : sans ce
        // recast, la comparaison stricte ci-dessous echoue toujours (int 70313 !== string '70313'),
        // et la fonction ne reconnait jamais avoir atteint $b.
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

$adjConfirme = [];
$poidsConfirme = [];
$retenus = [];
foreach ($ordre as $cle) {
    [$a, $b] = explode('|', $cle);
    $distDirecte = distance($coords, $a, $b);
    $alt = null !== $distDirecte ? plusCourtCheminConfirme($adjConfirme, $poidsConfirme, $a, $b) : null;
    if (null !== $alt && $alt <= $distDirecte * MARGE_RACCOURCI) {
        continue; // raccourci : un chemin via des aretes deja confirmees existe deja
    }
    $retenus[$cle] = $troncons[$cle];
    $d = $distDirecte ?? 0.0;
    $adjConfirme[$a][$b] = true;
    $adjConfirme[$b][$a] = true;
    $poidsConfirme[$a.'|'.$b] = $d;
    $poidsConfirme[$b.'|'.$a] = $d;
}
echo count($retenus)." troncons retenus apres reduction geometrique\n";

// ---- ecriture ----
$fichier = fopen(SORTIE, 'w');
fputcsv($fichier, ['route_label', 'zdc_a', 'zdc_b', 'nom_a', 'nom_b', 'duree_mediane_secondes', 'nb_observations']);
ksort($retenus);
foreach ($retenus as $cle => $durees) {
    [$a, $b] = explode('|', $cle);
    sort($durees);
    $n = count($durees);
    $mediane = $n > 0 ? ($n % 2 === 1 ? $durees[intdiv($n, 2)] : ($durees[$n / 2 - 1] + $durees[$n / 2]) / 2) : '';
    fputcsv($fichier, ['C', $a, $b, $nomParZdc[$a] ?? '', $nomParZdc[$b] ?? '', $mediane, $n]);
}
fclose($fichier);
echo "Ecrit dans ".SORTIE."\n";
