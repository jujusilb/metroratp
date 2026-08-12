<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplit Station::latitude/longitude (coordonnees geographiques reelles, WGS84) depuis
 * zdc_coordonnees.csv (extrait de stops.txt GTFS IDFM, location_type=1 = Zone de correspondance -
 * voir documentation/scripts/extraire_coordonnees_zdc.php ; le feed GTFS complet, ~1,3 Go, n'est
 * jamais commit), par codeExterne (ZdCId).
 *
 * Contrairement a schemaX/Y (app:importer-coordonnees-schema, plan officiel deforme, METRO
 * seulement, rapprochement par nom avec dictionnaire manuel), cette source couvre TOUS les modes
 * (bus compris) sur toute l'Ile-de-France et se rattache directement par identifiant fiable
 * (codeExterne), sans ambiguite de nom possible. C'est cette donnee qui alimente le fond de
 * carte reel (Leaflet/OSM) du calculateur de trajet.
 *
 * Deuxieme passe : les Stations "originales" sans codeExterne (doublons crees a la main avant
 * app:importer-reseau-complet, voir TODO.md) ne peuvent pas etre rattachees par ZdC - ce sont
 * pourtant des stations majeures (coeur historique du reseau metro/RER/tram), dont l'absence de
 * coordonnees viderait completement le trace du trajet mis en evidence sur la carte pour un
 * trajet metro classique. On leur copie les coordonnees de leur "jumelle" exacte (meme label,
 * deja positionnee par la premiere passe) quand elle est unique et non ambigue - meme
 * contournement que ConstruireCorrespondancesInterModesCommand (regroupement par label).
 *
 * Restreindre aux jumelles desservies par Metro/RER/Tramway serait plus sur en theorie, mais ne
 * fonctionne pas ici : meme les jumelles correctes (ex: "Nation", verifie manuellement) n'ont
 * qu'une Desserte de bus en base, le Desserte metro/RER/tram n'existant que sur la Station
 * "originale" elle-meme. Donc une petite marge d'erreur residuelle est possible (nom identique
 * mais lieu different, ex: "Saint-Paul" existe aussi comme un arret de bus rural sans rapport) -
 * EXCLUSIONS_CONNUES corrige les cas averes ; a completer si un nouveau cas est repere (verifier
 * une Station "originale" suspecte via ses voisins de Troncon, qui restent fiables).
 */
#[AsCommand(name: 'app:importer-coordonnees-geographiques', description: 'Importe les coordonnees GPS reelles des Stations depuis stops.txt (GTFS IDFM)')]
class ImporterCoordonneesGeographiquesCommand extends Command
{
    private const ZDC_COORDONNEES_CSV = 'documentation/scripts/donnees-extraites/zdc_coordonnees.csv';
    private const TAILLE_LOT = 1000;

    /**
     * Labels a exclure du repli par nom (2e passe) : verifie qu'aucune Station ZdC-liee portant
     * ce label n'est le meme lieu que la Station "originale" homonyme (ex: "Saint-Paul" du Marais
     * vs un arret de bus rural sans rapport, seul candidat ZdC-lie trouve pour ce label).
     */
    private const EXCLUSIONS_CONNUES = ['Saint-Paul'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    /**
     * @return \Generator<int, array<string, string>>
     */
    private function lireCsv(string $chemin): \Generator
    {
        $fichier = fopen($chemin, 'r');
        $header = fgetcsv($fichier);
        while (false !== ($ligne = fgetcsv($fichier))) {
            yield array_combine($header, $ligne);
        }
        fclose($fichier);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Lecture de zdc_coordonnees.csv...');
        $coordsParZdc = [];
        foreach ($this->lireCsv(self::ZDC_COORDONNEES_CSV) as $ligne) {
            $coordsParZdc[$ligne['zdc']] = [(float) $ligne['latitude'], (float) $ligne['longitude']];
        }
        $io->info(\count($coordsParZdc).' ZdC avec coordonnees.');

        $io->section('Mise a jour des Stations par codeExterne...');
        $nbMaj = 0;
        $nbEnAttente = 0;
        foreach ($connexion->executeQuery('SELECT id, code_externe FROM station WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $coords = $coordsParZdc[$row['code_externe']] ?? null;
            if (null === $coords) {
                continue;
            }
            [$lat, $lon] = $coords;
            $connexion->executeStatement('UPDATE station SET latitude = ?, longitude = ? WHERE id = ?', [$lat, $lon, (int) $row['id']]);
            ++$nbMaj;
            if (++$nbEnAttente >= self::TAILLE_LOT) {
                $nbEnAttente = 0;
                $io->write('.');
            }
        }
        $io->newLine();
        $io->info(sprintf('%d Stations positionnees par codeExterne.', $nbMaj));

        $io->section('Repli par nom pour les Stations sans codeExterne (doublons "originales")...');
        $coordsParLabel = [];
        foreach ($connexion->executeQuery('SELECT label, latitude, longitude FROM station WHERE latitude IS NOT NULL')->iterateAssociative() as $row) {
            $coordsParLabel[$row['label']][] = [(float) $row['latitude'], (float) $row['longitude']];
        }

        $nbMajParNom = 0;
        $nbAmbigues = 0;
        $nbIntrouvables = 0;
        foreach ($connexion->executeQuery('SELECT id, label FROM station WHERE latitude IS NULL')->iterateAssociative() as $row) {
            if (\in_array($row['label'], self::EXCLUSIONS_CONNUES, true)) {
                ++$nbIntrouvables;
                continue;
            }
            $candidats = $coordsParLabel[$row['label']] ?? [];
            $distincts = array_unique(array_map(static fn (array $c): string => implode(',', $c), $candidats));
            if (1 !== \count($distincts)) {
                if (\count($distincts) > 1) {
                    ++$nbAmbigues;
                }
                ++$nbIntrouvables;
                continue;
            }
            [$lat, $lon] = $candidats[0];
            $connexion->executeStatement('UPDATE station SET latitude = ?, longitude = ? WHERE id = ?', [$lat, $lon, (int) $row['id']]);
            ++$nbMajParNom;
        }

        $io->success(sprintf(
            '%d Stations supplementaires positionnees par nom (%d ambigues, %d sans jumelle trouvee - resteront sans coordonnees).',
            $nbMajParNom,
            $nbAmbigues,
            $nbIntrouvables - $nbAmbigues,
        ));

        return Command::SUCCESS;
    }
}
