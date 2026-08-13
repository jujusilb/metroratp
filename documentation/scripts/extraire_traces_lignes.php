<?php

/**
 * Extrait le trace geometrique reel (pas juste une ligne droite entre les stations) de chaque
 * Ligne depuis traces-des-lignes-de-transport-en-commun-idfm.csv (dataset IDFM, tous modes -
 * 1882 bus, 24 RER/Transilien, 16 metro, 17 tram, 2 funiculaire/telepherique).
 *
 * Source volumineuse (76 Mo, coordonnees a 6 decimales = precision ~11cm, et surtout une densite
 * de points bien plus fine que necessaire pour un affichage carte : 3,3M points au total, ~1700
 * par ligne en moyenne) et non commitee (dans documentation/IDFM-gtfs/, .gitignore). On simplifie
 * chaque trace (Douglas-Peucker, tolerance ~3m) en plus d'arrondir a 5 decimales (~1,1 m) : la
 * forme visuelle reste fidele au trace reel, seule la densite de points inutile disparait.
 *
 * Sortie : documentation/scripts/donnees-extraites/traces_lignes.csv
 * Colonnes : codeExterne,label,routeType,coordonnees (JSON : liste de lignes, chacune une liste de
 * [lon,lat])
 */

$source = 'documentation/IDFM-gtfs/traces-des-lignes-de-transport-en-commun-idfm.csv';
$sortie = 'documentation/scripts/donnees-extraites/traces_lignes.csv';
const DECIMALES = 5;
const TOLERANCE_METRES = 3.0;

/**
 * Distance perpendiculaire (metres, approx planaire - suffisant a l'echelle d'une simplification
 * de quelques metres) d'un point a la droite (a,b).
 */
function distancePerpendiculaire(array $p, array $a, array $b): float
{
    $echelleLon = 111320 * cos(deg2rad($a[1]));
    $px = ($p[0] - $a[0]) * $echelleLon;
    $py = ($p[1] - $a[1]) * 111320;
    $bx = ($b[0] - $a[0]) * $echelleLon;
    $by = ($b[1] - $a[1]) * 111320;

    $longueur = sqrt($bx ** 2 + $by ** 2);
    if ($longueur < 1e-9) {
        return sqrt($px ** 2 + $py ** 2);
    }

    return abs($px * $by - $py * $bx) / $longueur;
}

/**
 * Algorithme de Douglas-Peucker : reduit le nombre de points d'une ligne tout en preservant sa
 * forme visuelle (garde les points qui s'ecartent de plus de $tolerance metres de la droite
 * simplifiee locale).
 *
 * @param list<array{0: float, 1: float}> $points
 * @return list<array{0: float, 1: float}>
 */
function simplifier(array $points, float $tolerance): array
{
    $n = \count($points);
    if ($n < 3) {
        return $points;
    }

    $distanceMax = 0.0;
    $indexMax = 0;
    for ($i = 1; $i < $n - 1; ++$i) {
        $d = distancePerpendiculaire($points[$i], $points[0], $points[$n - 1]);
        if ($d > $distanceMax) {
            $distanceMax = $d;
            $indexMax = $i;
        }
    }

    if ($distanceMax > $tolerance) {
        $gauche = simplifier(\array_slice($points, 0, $indexMax + 1), $tolerance);
        $droite = simplifier(\array_slice($points, $indexMax), $tolerance);

        return [...\array_slice($gauche, 0, -1), ...$droite];
    }

    return [$points[0], $points[$n - 1]];
}

function arrondirCoordonnees($coordonnees)
{
    if (is_array($coordonnees) && 2 === \count($coordonnees) && is_numeric($coordonnees[0] ?? null) && is_numeric($coordonnees[1] ?? null)) {
        return [round($coordonnees[0], DECIMALES), round($coordonnees[1], DECIMALES)];
    }
    if (is_array($coordonnees)) {
        return array_map('arrondirCoordonnees', $coordonnees);
    }

    return $coordonnees;
}

$fichierSource = fopen($source, 'r');
$header = fgetcsv($fichierSource, 0, ';');
$header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
$idx = array_flip($header);

$fichierSortie = fopen($sortie, 'w');
fputcsv($fichierSortie, ['codeExterne', 'label', 'routeType', 'coordonnees']);

$nb = 0;
$nbSansTrace = 0;
while (($row = fgetcsv($fichierSource, 0, ';')) !== false) {
    $codeExterne = str_replace('IDFM:', '', $row[$idx['ID']] ?? '');
    $label = $row[$idx['Short Name']] ?? '';
    $routeType = $row[$idx['Route Type']] ?? '';
    $shapeJson = $row[$idx['Shape']] ?? '';

    if ('' === $shapeJson) {
        ++$nbSansTrace;
        continue;
    }

    $shape = json_decode($shapeJson, true);
    $coordonnees = $shape['coordinates'] ?? null;
    if (null === $coordonnees) {
        ++$nbSansTrace;
        continue;
    }

    $coordonneesSimplifiees = array_map(
        static fn (array $ligne) => simplifier($ligne, TOLERANCE_METRES),
        $coordonnees,
    );
    $coordonneesArrondies = arrondirCoordonnees($coordonneesSimplifiees);
    fputcsv($fichierSortie, [$codeExterne, $label, $routeType, json_encode($coordonneesArrondies, JSON_THROW_ON_ERROR)]);
    ++$nb;
}
fclose($fichierSource);
fclose($fichierSortie);

echo "$nb traces ecrites dans $sortie ($nbSansTrace lignes sans trace ignorees).\n";
echo 'Taille : ' . round(filesize($sortie) / 1024 / 1024, 1) . " Mo.\n";
