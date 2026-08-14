<?php

/**
 * Extrait juste les colonnes Nom_commune/Code_departement de communes-par-contrat.csv (dataset
 * IDFM, 55 Mo a cause de la colonne Geo Shape) pour produire un petit CSV commit-able, utilise
 * par app:importer-plans-secteur pour deriver le departement d'une Station a partir de
 * Station::ville (commune).
 *
 * Sortie deduppliquee sur (commune, departement) sans resoudre les ambiguites ici : une commune
 * associee a plusieurs departements distincts (rare, communes limitrophes) doit rester visible
 * pour que l'import puisse la traiter comme non-fiable.
 */
$source = __DIR__.'/../IDFM-gtfs/communes-par-contrat.csv';
$destination = __DIR__.'/donnees-extraites/communes_departements.csv';

$entree = fopen($source, 'r');
$header = fgetcsv($entree, 0, ';');
$header[0] = preg_replace('/^\x{FEFF}+/u', '', $header[0]);
$idx = array_flip($header);

$paires = [];
while (($ligne = fgetcsv($entree, 0, ';')) !== false) {
    $commune = trim($ligne[$idx['Nom_commune']]);
    $departement = trim($ligne[$idx['Code_departement']]);
    if ('' === $commune || '' === $departement) {
        continue;
    }
    $paires[$commune.'|'.$departement] = [$commune, $departement];
}
fclose($entree);

ksort($paires);

$sortie = fopen($destination, 'w');
fputcsv($sortie, ['commune', 'departement']);
foreach ($paires as [$commune, $departement]) {
    fputcsv($sortie, [$commune, $departement]);
}
fclose($sortie);

echo count($paires)." paires commune/departement ecrites dans $destination\n";
