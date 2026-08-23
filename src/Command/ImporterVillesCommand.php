<?php

namespace App\Command;

use App\Entity\Ville;
use App\Repository\StationRepository;
use App\Repository\VilleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les Ville (communes d'Ile-de-France, referentiel officiel geo.api.gouv.fr - voir
 * documentation/geo-communes/, 8 departements 75/77/78/91/92/93/94/95, 1266 communes), puis
 * rattache Station::villeRef par correspondance de nom depuis Station::ville (texte libre,
 * inchange - voir app:importer-communes-stations). Idempotent : upsert des Ville par
 * codeInsee, recalcule le rattachement Station a chaque execution.
 *
 * Paris (arrondissements) : "Paris 1er".."Paris 20e" et "Paris" pointent tous vers la seule
 * commune "Paris" (75056) - un seul contour dans ce referentiel, les arrondissements ne sont
 * pas des communes.
 *
 * Corrections manuelles (communes renommees/fusionnees depuis l'import Station::ville
 * d'origine, verifiees une a une contre le referentiel actuel) :
 * - "Saint-Ouen" -> "Saint-Ouen-sur-Seine" (93070)
 * - "Chesnay-Rocquencourt" -> "Le Chesnay-Rocquencourt" (78158)
 * - "Herblay" -> "Herblay-sur-Seine" (95306)
 * - "Evry-Courcouronnes" -> "Évry-Courcouronnes" (accent)
 *
 * Residuel attendu : stations dont la commune est hors Ile-de-France (reseau Transilien/bus qui
 * depasse la region - Chartres, Sens, Chateau-Thierry...), absentes de ce referentiel par choix
 * de perimetre (voir documentation/TODO.md) - villeRef reste null, ce n'est pas un bug.
 *
 * Homonymes reels (4 noms partages par 2 communes distinctes parmi les 1266 - Blandy,
 * Marolles-en-Brie, Mondreville, Saint-Martin-des-Champs) : desambiguises par test
 * point-dans-polygone (isDansFrontiere()) grace aux coordonnees de la Station, plutot qu'un
 * rattachement par nom seul qui choisirait arbitrairement l'une des deux.
 */
#[AsCommand(name: 'app:importer-villes', description: "Importe les Ville (communes IDF, geo.api.gouv.fr) et rattache Station::villeRef")]
class ImporterVillesCommand extends Command
{
    private const GEOJSON_FICHIERS = [
        'documentation/geo-communes/communes-75.geojson',
        'documentation/geo-communes/communes-77.geojson',
        'documentation/geo-communes/communes-78.geojson',
        'documentation/geo-communes/communes-91.geojson',
        'documentation/geo-communes/communes-92.geojson',
        'documentation/geo-communes/communes-93.geojson',
        'documentation/geo-communes/communes-94.geojson',
        'documentation/geo-communes/communes-95.geojson',
    ];

    private const CORRECTIONS_MANUELLES = [
        'Saint-Ouen' => 'Saint-Ouen-sur-Seine',
        'Chesnay-Rocquencourt' => 'Le Chesnay-Rocquencourt',
        'Herblay' => 'Herblay-sur-Seine',
        'Evry-Courcouronnes' => 'Évry-Courcouronnes',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VilleRepository $villeRepository,
        private readonly StationRepository $stationRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Import des Ville depuis geo.api.gouv.fr');
        $villesParLabel = [];
        $nbCrees = 0;
        $nbMisAJour = 0;
        $nbTotal = 0;
        foreach (self::GEOJSON_FICHIERS as $fichier) {
            $data = json_decode(file_get_contents($fichier), true, flags: JSON_THROW_ON_ERROR);
            foreach ($data['features'] as $feature) {
                $codeInsee = $feature['properties']['code'];
                $label = $feature['properties']['nom'];

                $ville = $this->villeRepository->findOneBy(['codeInsee' => $codeInsee]);
                if (null === $ville) {
                    $ville = new Ville();
                    $ville->setCodeInsee($codeInsee);
                    $this->entityManager->persist($ville);
                    ++$nbCrees;
                } else {
                    ++$nbMisAJour;
                }
                $ville->setLabel($label);
                $ville->setFrontiere($feature['geometry']);
                $villesParLabel[$label][] = $ville;
                ++$nbTotal;
            }
        }
        $this->entityManager->flush();
        $io->writeln(sprintf('%d Ville creees, %d mises a jour (%d au total).', $nbCrees, $nbMisAJour, $nbTotal));

        $io->section('Rattachement de Station::villeRef');
        $paris = $villesParLabel['Paris'][0] ?? null;
        $nbRattachees = 0;
        $nbSansCorrespondance = 0;
        $nbAmbigues = 0;
        $compteur = 0;
        foreach ($this->stationRepository->findAll() as $station) {
            $ville = $station->getVille();
            if (null === $ville || '' === $ville) {
                continue;
            }

            $candidats = null;
            if ('Paris' === $ville || 1 === preg_match('/^Paris \d/', $ville)) {
                $candidats = null !== $paris ? [$paris] : null;
            } elseif (isset($villesParLabel[$ville])) {
                $candidats = $villesParLabel[$ville];
            } elseif (isset(self::CORRECTIONS_MANUELLES[$ville], $villesParLabel[self::CORRECTIONS_MANUELLES[$ville]])) {
                $candidats = $villesParLabel[self::CORRECTIONS_MANUELLES[$ville]];
            }

            if (null === $candidats) {
                ++$nbSansCorrespondance;
                continue;
            }

            $cible = $candidats[0];
            if (count($candidats) > 1) {
                $cible = $this->desambiguiserParPosition($candidats, $station->getLatitude(), $station->getLongitude());
                if (null === $cible) {
                    ++$nbAmbigues;
                    continue;
                }
            }

            $station->setVilleRef($cible);
            ++$nbRattachees;

            if (0 === (++$compteur % 3000)) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d Station rattachees a leur Ville. %d sans correspondance (commune hors Ile-de-France ou perimetre des donnees de frontiere, voir documentation/TODO.md). %d homonymes non tranches (pas de coordonnees ou point hors des deux polygones).',
            $nbRattachees,
            $nbSansCorrespondance,
            $nbAmbigues,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param Ville[] $candidats plusieurs communes distinctes partageant le meme nom (voir la
     *                           liste en en-tete de classe) - tranche par test point-dans-polygone
     *                           plutot que de choisir arbitrairement.
     */
    private function desambiguiserParPosition(array $candidats, ?float $latitude, ?float $longitude): ?Ville
    {
        if (null === $latitude || null === $longitude) {
            return null;
        }

        $trouve = null;
        foreach ($candidats as $ville) {
            if ($this->pointDansGeometrie($longitude, $latitude, $ville->getFrontiere())) {
                if (null !== $trouve) {
                    return null; // point dans 2 polygones a la fois : pas cense arriver, on ne tranche pas
                }
                $trouve = $ville;
            }
        }

        return $trouve;
    }

    private function pointDansGeometrie(float $lon, float $lat, ?array $geometrie): bool
    {
        if (null === $geometrie) {
            return false;
        }

        $polygones = 'MultiPolygon' === $geometrie['type']
            ? $geometrie['coordinates']
            : [$geometrie['coordinates']];

        foreach ($polygones as $anneaux) {
            // Anneau exterieur seulement (index 0) : les eventuels trous (enclaves) sont ignores,
            // approximation jugee suffisante pour departager 2 communes homonymes.
            if ($this->pointDansAnneau($lon, $lat, $anneaux[0] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ray casting standard (pair/impair) sur un anneau de polygone GeoJSON ([lon, lat] par point).
     */
    private function pointDansAnneau(float $lon, float $lat, array $anneau): bool
    {
        $dedans = false;
        $nb = count($anneau);
        for ($i = 0, $j = $nb - 1; $i < $nb; $j = $i++) {
            [$xi, $yi] = $anneau[$i];
            [$xj, $yj] = $anneau[$j];
            $intersecte = ($yi > $lat) !== ($yj > $lat)
                && $lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi;
            if ($intersecte) {
                $dedans = !$dedans;
            }
        }

        return $dedans;
    }
}
