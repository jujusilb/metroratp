<?php

/**
 * Extrait, depuis positionnement-dans-la-rame.csv (IDFM), les VRAIS lieux/points d'interet
 * (musees, monuments, hopitaux, jardins...) plutot que les simples adresses de rue/avenue/place
 * deja presentes ailleurs (Acces.label). Idee : le champ to_name (destination), pour les lignes
 * to_type=access_point, contient soit une adresse de rue (deja connue via Acces), soit - quand la
 * sortie mene a un lieu remarquable - le nom de ce lieu (ex: "Tour Eiffel", "Hopital Kremlin
 * Bicetre", "Manufacture des Gobelins"). Filtre par expression reguliere pour ne garder que les
 * seconds (verifie manuellement : 117 lieux distincts sur 1018 noms, tres peu de faux positifs
 * residuels).
 *
 * Rattachement a la Station : via from_id (le quai/stop_point, deja rattache a sa ZdC comme dans
 * extraire_conseils_position.php) - le lieu est "a proximite" de la Station ou l'on monte pour y
 * acceder, pas de l'Acces precis lui-meme (plus simple, suffisant pour l'affichage sur la fiche
 * Station).
 *
 * Sortie : documentation/scripts/donnees-extraites/points_interet.csv
 * Colonnes : zdc,label
 */

$gtfsDir = 'documentation/IDFM-gtfs/csv/';
$sortie = 'documentation/scripts/donnees-extraites/points_interet.csv';

function lireCsv(string $chemin, string $separateur = ','): \Generator
{
    $f = fopen($chemin, 'r');
    $header = fgetcsv($f, 0, $separateur);
    $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
    $idx = array_flip($header);
    while (($row = fgetcsv($f, 0, $separateur)) !== false) {
        $assoc = [];
        foreach ($idx as $col => $i) {
            $assoc[$col] = $row[$i] ?? '';
        }
        yield $assoc;
    }
    fclose($f);
}

// Motifs typiques d'une adresse de rue / d'un point d'acces generique (pas un vrai lieu notable).
$motifsRue = '/^(\d+[\d\s,\-]*\s*(bis|ter)?[,\s]*)?(rue|r\.\s|avenue|av\.?|place|pl\.?|boulevard|bd\.?|impasse|all[ée]e|all\.?|square|quai|cours|esplanade|passage|villa|sentier|chemin|voie|route|rte\.?|promenade|rond-point|face\s+au|t?terre-plein|angle|entr[ée]e|sortie|gare\s+routi|gare\s+sncf|parvis(?!\s+de\s+notre)|parking)/iu';
$suffixeVoie = '/\(.*(rue|avenue|av\.?|place|pl\.?|boulevard|bd\.?)\b[^)]*\)\s*$/iu';
// Generique / infrastructure de transport sans nom propre notable (pas un lieu a visiter) :
// exclu explicitement plutot que par regex, trop irregulier pour un motif fiable.
$denylist = [
    'Gare routière', 'Gare Routière', 'Gare SNCF', 'Gare Routière Bus',
    "SNCF/RER C grandes lignes et banlieue", 'Entrée / Sortie', 'Sortie Parking',
    'Parking SNCF côté arrivée', 'Centre Commercial', 'Parvis',
];

echo "Association stop_id -> ZdC (stops.txt, parent_station)...\n";
$zdcConnus = [];
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    if ('1' === $row['location_type']) {
        $zdcConnus[str_replace('IDFM:', '', $row['stop_id'])] = true;
    }
}
$stopVersZdc = [];
foreach (lireCsv($gtfsDir . 'stops.txt') as $row) {
    $id = str_replace('IDFM:', '', $row['stop_id']);
    $parent = str_replace('IDFM:', '', $row['parent_station']);
    if (isset($zdcConnus[$parent])) {
        $stopVersZdc[$id] = $parent;
    }
}
echo count($stopVersZdc) . " arrets rattaches a une ZdC.\n";

echo "Lecture de positionnement-dans-la-rame.csv...\n";
$paires = []; // "zdc|label" => true (dedoublonne)
$nbLignes = 0;
foreach (lireCsv($gtfsDir . 'positionnement-dans-la-rame.csv', ';') as $row) {
    ++$nbLignes;
    if ('access_point' !== $row['to_type']) {
        continue;
    }
    $label = trim($row['to_name']);
    if ('' === $label || preg_match($motifsRue, $label) || preg_match($suffixeVoie, $label)) {
        continue;
    }
    $estDansDenylist = false;
    foreach ($denylist as $motif) {
        if (str_starts_with($label, $motif)) {
            $estDansDenylist = true;
            break;
        }
    }
    if ($estDansDenylist) {
        continue;
    }
    $zdc = $stopVersZdc[$row['from_id']] ?? null;
    if (null === $zdc) {
        continue;
    }
    $paires[$zdc . '|' . $label] = [$zdc, $label];
}
echo "$nbLignes lignes source lues.\n";
echo count($paires) . " paires (ZdC, lieu) distinctes retenues.\n";

$fichier = fopen($sortie, 'w');
fputcsv($fichier, ['zdc', 'label']);
foreach ($paires as [$zdc, $label]) {
    fputcsv($fichier, [$zdc, $label]);
}
fclose($fichier);

echo "Ecrit dans $sortie.\n";
