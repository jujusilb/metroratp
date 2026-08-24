<?php

namespace App\Command;

use App\Entity\Defibrillateur;
use App\Entity\EquipementArret;
use App\Entity\PointDeVente;
use App\Entity\Station;
use App\Entity\Utilisateur;
use App\Entity\Ville;
use App\Repository\DefibrillateurRepository;
use App\Repository\EquipementArretRepository;
use App\Repository\PointDeVenteRepository;
use App\Repository\StationRepository;
use App\Repository\UtilisateurRepository;
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
 * rattache villeRef sur les 5 entites qui ont un champ ville en texte libre (inchange) : Station,
 * Defibrillateur, EquipementArret, PointDeVente, Utilisateur. Idempotent : upsert des Ville par
 * codeInsee, recalcule chaque rattachement a chaque execution.
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
#[AsCommand(name: 'app:importer-villes', description: 'Importe les Ville (communes IDF, geo.api.gouv.fr) et rattache villeRef sur Station/Defibrillateur/EquipementArret/PointDeVente/Utilisateur')]
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
        private readonly DefibrillateurRepository $defibrillateurRepository,
        private readonly EquipementArretRepository $equipementArretRepository,
        private readonly PointDeVenteRepository $pointDeVenteRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
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
                $ville->setCodesPostaux($feature['properties']['codesPostaux'] ?? null);
                $villesParLabel[self::normaliserCle($label)][] = $ville;
                ++$nbTotal;
            }
        }
        $this->entityManager->flush();
        $io->writeln(sprintf('%d Ville creees, %d mises a jour (%d au total).', $nbCrees, $nbMisAJour, $nbTotal));

        $paris = $villesParLabel[self::normaliserCle('Paris')][0] ?? null;

        $io->section('Rattachement de Station::villeRef');
        $resultat = $this->rattacherEntites(
            $this->stationRepository->findAll(),
            $villesParLabel,
            $paris,
            static fn (Station $s): ?string => $s->getVille(),
            static fn (Station $s, ?Ville $v) => $s->setVilleRef($v),
            static fn (Station $s): ?float => $s->getLatitude(),
            static fn (Station $s): ?float => $s->getLongitude(),
        );
        $this->rapporterResultat($io, 'Station', $resultat);

        $io->section('Rattachement de Defibrillateur::villeRef');
        $resultat = $this->rattacherEntites(
            $this->defibrillateurRepository->findAll(),
            $villesParLabel,
            $paris,
            static fn (Defibrillateur $d): ?string => $d->getVille(),
            static fn (Defibrillateur $d, ?Ville $v) => $d->setVilleRef($v),
            static fn (Defibrillateur $d): ?float => $d->getLatitude(),
            static fn (Defibrillateur $d): ?float => $d->getLongitude(),
        );
        $this->rapporterResultat($io, 'Defibrillateur', $resultat);

        $io->section('Rattachement de EquipementArret::villeRef');
        $resultat = $this->rattacherEntites(
            $this->equipementArretRepository->findAll(),
            $villesParLabel,
            $paris,
            static fn (EquipementArret $e): ?string => $e->getVille(),
            static fn (EquipementArret $e, ?Ville $v) => $e->setVilleRef($v),
            static fn (EquipementArret $e): ?float => $e->getLatitude(),
            static fn (EquipementArret $e): ?float => $e->getLongitude(),
        );
        $this->rapporterResultat($io, 'EquipementArret', $resultat);

        $io->section('Rattachement de PointDeVente::villeRef');
        $resultat = $this->rattacherEntites(
            $this->pointDeVenteRepository->findAll(),
            $villesParLabel,
            $paris,
            static fn (PointDeVente $p): ?string => $p->getVille(),
            static fn (PointDeVente $p, ?Ville $v) => $p->setVilleRef($v),
            static fn (PointDeVente $p): ?float => $p->getLatitude(),
            static fn (PointDeVente $p): ?float => $p->getLongitude(),
        );
        $this->rapporterResultat($io, 'PointDeVente', $resultat);

        $io->section('Rattachement de Utilisateur::villeRef');
        // Pas de coordonnees GPS sur Utilisateur (profil, pas un lieu) : les 4 homonymes reels
        // (Blandy, Marolles-en-Brie, Mondreville, Saint-Martin-des-Champs) restent non tranches
        // si un Utilisateur y habite - cas tres marginal, laisse tel quel plutot que de deviner.
        $resultat = $this->rattacherEntites(
            $this->utilisateurRepository->findAll(),
            $villesParLabel,
            $paris,
            static fn (Utilisateur $u): ?string => $u->getVille(),
            static fn (Utilisateur $u, ?Ville $v) => $u->setVilleRef($v),
            static fn (Utilisateur $u): ?float => null,
            static fn (Utilisateur $u): ?float => null,
        );
        $this->rapporterResultat($io, 'Utilisateur', $resultat);

        return Command::SUCCESS;
    }

    /**
     * @param array{rattachees: int, sansCorrespondance: int, ambigues: int} $resultat
     */
    private function rapporterResultat(SymfonyStyle $io, string $nomEntite, array $resultat): void
    {
        $io->success(sprintf(
            '%d %s rattachee(s) a leur Ville. %d sans correspondance (commune hors Ile-de-France ou perimetre des donnees de frontiere, voir documentation/TODO.md). %d homonymes non tranches.',
            $resultat['rattachees'],
            $nomEntite,
            $resultat['sansCorrespondance'],
            $resultat['ambigues'],
        ));
    }

    /**
     * Logique de rattachement partagee entre Station et les 4 autres entites avec un champ ville
     * (Defibrillateur, EquipementArret, PointDeVente, Utilisateur) : meme correspondance de nom,
     * memes corrections manuelles, meme desambiguisation des homonymes par position quand
     * possible (getLatitude/getLongitude renvoient null pour Utilisateur, qui n'a pas de
     * coordonnees - ces cas restent alors non tranches plutot que de deviner).
     *
     * @template T of object
     *
     * @param iterable<T>         $entites
     * @param array<string, Ville[]> $villesParLabel
     * @param callable(T): ?string    $getVille
     * @param callable(T, ?Ville): void $setVilleRef
     * @param callable(T): ?float     $getLatitude
     * @param callable(T): ?float     $getLongitude
     *
     * @return array{rattachees: int, sansCorrespondance: int, ambigues: int}
     */
    private function rattacherEntites(
        iterable $entites,
        array $villesParLabel,
        ?Ville $paris,
        callable $getVille,
        callable $setVilleRef,
        callable $getLatitude,
        callable $getLongitude,
    ): array {
        $nbRattachees = 0;
        $nbSansCorrespondance = 0;
        $nbAmbigues = 0;
        $compteur = 0;

        foreach ($entites as $entite) {
            $ville = $getVille($entite);
            if (null === $ville || '' === $ville) {
                continue;
            }

            $villeCle = self::normaliserCle($ville);
            $candidats = null;
            if (1 === preg_match('/^PARIS(\s+\d.*)?$/u', $villeCle)) {
                $candidats = null !== $paris ? [$paris] : null;
            } elseif (isset($villesParLabel[$villeCle])) {
                $candidats = $villesParLabel[$villeCle];
            } elseif (isset(self::CORRECTIONS_MANUELLES[$ville])) {
                $cleCorrigee = self::normaliserCle(self::CORRECTIONS_MANUELLES[$ville]);
                $candidats = $villesParLabel[$cleCorrigee] ?? null;
            }

            if (null === $candidats) {
                ++$nbSansCorrespondance;
                continue;
            }

            $cible = $candidats[0];
            if (count($candidats) > 1) {
                $cible = $this->desambiguiserParPosition($candidats, $getLatitude($entite), $getLongitude($entite));
                if (null === $cible) {
                    ++$nbAmbigues;
                    continue;
                }
            }

            $setVilleRef($entite, $cible);
            ++$nbRattachees;

            if (0 === (++$compteur % 3000)) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        return ['rattachees' => $nbRattachees, 'sansCorrespondance' => $nbSansCorrespondance, 'ambigues' => $nbAmbigues];
    }

    /**
     * Normalise une chaine pour la comparaison de noms de commune (insensible a la casse) : la
     * source geo.api.gouv.fr est en casse normale ("Créteil"), mais certaines de nos donnees
     * stockent le nom de ville tout en majuscules (ex: PointDeVente::ville, "CRÉTEIL") - sans
     * cette normalisation, ces entites ne trouvaient jamais de correspondance (0,2% de taux de
     * rattachement au lieu de 30%+ attendu, decouvert le 2026-08-24).
     */
    private static function normaliserCle(string $s): string
    {
        return mb_strtoupper(trim($s), 'UTF-8');
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
