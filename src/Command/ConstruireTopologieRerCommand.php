<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Direction;
use App\Entity\Ligne;
use App\Entity\Mission;
use App\Entity\Service;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use App\Entity\TypeTroncon;
use App\Repository\LigneRepository;
use App\Repository\ServiceRepository;
use App\Repository\TypeDesserteRepository;
use App\Repository\TypeTronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Construit les troncons/directions/missions des RER A/B/C/D/E, dont les lignes/stations/dessertes
 * existent deja (app:importer-lignes-rer) mais n'avaient jusqu'ici AUCUN troncon (voir
 * documentation/TODO.md) : chaque station RER etait une ile isolee dans le graphe de trajet.
 *
 * La ligne C (documentation/scripts/extraire_troncons_rer_c.php, fichier separe car extrait plus
 * tard que A/B/D/E) est un cas particulier : sa reduction geometrique brute laissait 10 aretes en
 * trop (84 au lieu des 74 attendues pour un arbre a 75 stations), a cause de plusieurs missions
 * semi-directes qui se chevauchent sur le corridor Paris<->Choisy-le-Roi (Ivry/Vitry/Ardoines) avec
 * des sauts de longueurs differentes — l'algorithme "plus long d'abord" (valide sur A/B/D/E) se
 * faisait tromper par un raccourci non encore retire servant de faux chemin alternatif court. Voir
 * le script pour l'algorithme "plus court d'abord, contre un graphe deja confirme" qui corrige ca.
 * Une fois corrige, la ligne C est un arbre pur (verifie : 74 troncons = 75 stations - 1, aucun
 * maillage comme celui de D), avec 6 vrais terminus (confirmes contre le plan officiel,
 * documentation/PLAN/plan-de-ligne_rer_ligne-c.*.png) : Pontoise, Versailles-Chateau-Rive-Gauche,
 * Saint-Quentin-en-Yvelines, Massy-Palaiseau, Saint-Martin-d'Etampes, Dourdan-la-Foret.
 *
 * Topologie extraite du GTFS complet (documentation/scripts/extraire_troncons_rer.py) : contrairement
 * aux trams (trip_headsign = vrai nom de destination), le RER utilise des codes mission SNCF/RATP
 * (4 lettres, ex: TAXE, UZAR) inexploitables pour regrouper des trajets par branche. La topologie est
 * donc deduite du graphe physique reel (union des paires de stations consecutives, avec reduction
 * geometrique pour eliminer les "raccourcis" des trains express qui sautent des gares), verifiee
 * ensuite contre les plans officiels RATP fournis par l'utilisateur (documentation/PLAN/PDF/RER).
 * Les stations existantes sont associees aux ZdCId GTFS par correspondance de nom, mais UNIQUEMENT
 * au sein des dessertes de chaque ligne (documentation/scripts/associer_stations_rer.py) — jamais
 * sur l'ensemble des ~14000 stations nationales, contrairement au premier essai d'
 * ImporterReseauCompletCommand qui avait corrompu des identites de stations par des collisions de
 * noms sans rapport (voir son docblock).
 *
 * Le modele Direction/tronçon (comme pour les trams) suppose un ARBRE : une Direction par terminus
 * reel, un numero de tronçon assigne une fois par branche et reutilise par toutes les directions qui
 * la traversent. A/B/E sont de vrais arbres (verifie : nombre de tronçons = nombre de stations - 1).
 * D a un vrai maillage local avec cycles autour d'Évry/Corbeil/Juvisy (2 itineraires paralleles a
 * deux endroits) que ce modele ne peut pas representer : cette zone est volontairement exclue (voir
 * documentation/TODO.md, section RER D), le reste de la ligne (tronc Creil<->Villeneuve-Saint-Georges,
 * branche Corbeil-Essonnes<->Malesherbes) est construit normalement mais reste deconnecte du maillage
 * tant qu'il n'est pas traite.
 *
 * A usage unique : refuse de s'executer si une ligne a deja des troncons.
 */
#[AsCommand(name: 'app:construire-topologie-rer', description: 'Construit les troncons/missions des RER A/B/C/D/E (hors maillage Evry/Corbeil/Juvisy sur D)')]
class ConstruireTopologieRerCommand extends Command
{
    /** @var string[] fichiers a fusionner (memes colonnes) : A/B/D/E extraits ensemble, C separement */
    private const TRONCONS_CSV = [
        'documentation/scripts/donnees-extraites/troncons_rer.csv',
        'documentation/scripts/donnees-extraites/troncons_rer_c.csv',
    ];

    /**
     * Paires manuelles (nom GTFS => label DB) : memes lieux reels, noms trop differents pour la
     * normalisation automatique (abreviation "CDG" vs "Charles de Gaulle", suffixe "- RER"/"TGV").
     * Voir documentation/scripts/associer_stations_rer.py (verifie une par une a l'origine).
     */
    private const ASSOCIATIONS_MANUELLES = [
        'Aéroport CDG 1 (Terminal 3)' => 'Aéroport CDG 1 (Terminal 3) - RER',
        'Aéroport CDG - Terminal 2 (TGV)' => 'Aéroport Charles de Gaulle 2 (Terminal 2)',
        // Ligne C : nom GTFS actuel different du nom stocke lors de l'import initial (meme lieu reel).
        'Chamarande' => 'Gare de Chamarande',
        'Thiais - Orly (Pont de Rungis)' => 'Pont de Rungis Aéroport d\'Orly',
    ];

    /** @var string[] codeExterne (route_id IDFM) de chaque ligne, voir backfill du 2026-08-09 */
    private const LIGNES_CODES = [
        'A' => 'C01742',
        'B' => 'C01743',
        'C' => 'C01727',
        'D' => 'C01728',
        'E' => 'C01729',
    ];

    /**
     * Pour la ligne D uniquement : ZdCId ou arreter la construction (le reste, cote maillage
     * Evry/Corbeil/Juvisy, est volontairement exclu). Deux racines separees sont construites :
     * Creil (s'arrete a Villeneuve-Saint-Georges) et Malesherbes (s'arrete a Corbeil-Essonnes).
     */
    private const D_LIMITES = ['69568' => true, '60309' => true]; // Villeneuve-Saint-Georges, Corbeil-Essonnes

    private TypeDesserte $depart;
    private TypeDesserte $arrivee;
    private TypeTroncon $exterieur;
    private Service $serviceUnique;

    /** @var array<int, TronconDesserte> cle = spl_object_id(troncon).'|'.spl_object_id(desserte) pour role Depart */
    private array $departTdCache = [];

    /** @var int compteur de troncons crees, pour le rapport final */
    private int $nbTronconsCrees = 0;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LigneRepository $ligneRepository,
        private readonly TypeDesserteRepository $typeDesserteRepository,
        private readonly TypeTronconRepository $typeTronconRepository,
        private readonly ServiceRepository $serviceRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->depart = $this->typeDesserteRepository->findOneBy(['label' => 'Départ']);
        $this->arrivee = $this->typeDesserteRepository->findOneBy(['label' => 'Arrivée']);
        $this->exterieur = $this->typeTronconRepository->findOneBy(['label' => 'Exterieur']);
        $this->serviceUnique = $this->serviceRepository->findOneBy(['label' => 'Unique']);

        $dessertesParZdc = $this->chargerDessertesParZdc($io, $this->chargerNomsParZdc());
        if (null === $dessertesParZdc) {
            return Command::FAILURE;
        }

        [$adjacence, $duree] = $this->chargerGraphe();

        foreach (['A', 'B', 'C', 'E'] as $label) {
            $this->construireArbreSimple($io, $label, $adjacence[$label] ?? [], $duree[$label] ?? [], $dessertesParZdc[$label] ?? []);
        }

        $this->construireRerD($io, $adjacence['D'] ?? [], $duree['D'] ?? [], $dessertesParZdc['D'] ?? []);

        $this->entityManager->flush();
        $io->success(sprintf('Topologie des RER A/B/C/D/E construite : %d troncons au total (hors maillage Evry/Corbeil/Juvisy sur D, voir TODO.md).', $this->nbTronconsCrees));

        return Command::SUCCESS;
    }

    /**
     * Associe chaque ZdCId GTFS a sa Desserte existante par correspondance de nom, mais
     * UNIQUEMENT au sein des dessertes de la ligne concernee (26 a 59 stations) plutot que sur
     * l'ensemble des ~14000 stations nationales : voir le docblock de la classe. Fait a
     * l'execution (pas via un CSV precalcule) car les id de Station different entre
     * environnements (ordre d'auto-increment local vs prod) — seuls les LABELS sont stables.
     *
     * @return ?array<string, array<string, Desserte>> route_label => zdc_id => Desserte, ou null si erreur
     */
    private function chargerDessertesParZdc(SymfonyStyle $io, array $nomsParZdc): ?array
    {
        $resultat = [];

        foreach ($nomsParZdc as $routeLabel => $noms) {
            $ligne = $this->trouverLigne($routeLabel);
            if (null === $ligne) {
                $io->error("Ligne $routeLabel introuvable (codeExterne manquant ?).");

                return null;
            }

            $dessertesParNomNormalise = [];
            foreach ($ligne->getDessertes() as $desserte) {
                $label = $desserte->getStation()?->getLabel();
                if (null !== $label) {
                    $dessertesParNomNormalise[$this->normaliser($label)][] = $desserte;
                }
            }

            foreach ($noms as $zdcId => $nomGtfs) {
                $nomCherche = self::ASSOCIATIONS_MANUELLES[$nomGtfs] ?? $nomGtfs;
                $candidats = $dessertesParNomNormalise[$this->normaliser($nomCherche)] ?? [];

                if (1 !== \count($candidats)) {
                    $io->error(sprintf(
                        'Ligne %s : %d desserte(s) trouvee(s) pour "%s" (zdc %s), attendu exactement 1.',
                        $routeLabel,
                        \count($candidats),
                        $nomGtfs,
                        $zdcId,
                    ));

                    return null;
                }

                $resultat[$routeLabel][$zdcId] = $candidats[0];
            }
        }

        return $resultat;
    }

    private function normaliser(string $texte): string
    {
        $translitere = @iconv('UTF-8', 'ASCII//TRANSLIT', $texte);
        $minuscule = mb_strtolower(false !== $translitere ? $translitere : $texte);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $minuscule));
    }

    /**
     * @return array<string, array<string, string>> route_label => zdc_id => nom GTFS
     */
    private function chargerNomsParZdc(): array
    {
        $resultat = [];
        foreach (self::TRONCONS_CSV as $chemin) {
            $fichier = fopen($chemin, 'r');
            fgetcsv($fichier); // en-tete
            while (false !== ($ligneCsv = fgetcsv($fichier))) {
                [$routeLabel, $zdcA, $zdcB, $nomA, $nomB] = $ligneCsv;
                $resultat[$routeLabel][$zdcA] = $nomA;
                $resultat[$routeLabel][$zdcB] = $nomB;
            }
            fclose($fichier);
        }

        return $resultat;
    }

    /**
     * @return array{0: array<string, array<string, string[]>>, 1: array<string, array<string, int>>}
     *         [adjacence[route][zdc] = liste de zdc voisins, duree[route]["zdcA|zdcB"] = secondes]
     */
    private function chargerGraphe(): array
    {
        $adjacence = [];
        $duree = [];

        foreach (self::TRONCONS_CSV as $chemin) {
            $fichier = fopen($chemin, 'r');
            fgetcsv($fichier); // en-tete
            while (false !== ($ligneCsv = fgetcsv($fichier))) {
                [$routeLabel, $zdcA, $zdcB, , , $dureeMediane] = $ligneCsv;

                $adjacence[$routeLabel][$zdcA][] = $zdcB;
                $adjacence[$routeLabel][$zdcB][] = $zdcA;

                if ('' !== $dureeMediane) {
                    $duree[$routeLabel][$zdcA.'|'.$zdcB] = (int) $dureeMediane;
                    $duree[$routeLabel][$zdcB.'|'.$zdcA] = (int) $dureeMediane;
                }
            }
            fclose($fichier);
        }

        return [$adjacence, $duree];
    }

    private function trouverLigne(string $label): ?Ligne
    {
        return $this->ligneRepository->findOneBy(['codeExterne' => self::LIGNES_CODES[$label]]);
    }

    private function dejaConstruite(Ligne $ligne): bool
    {
        return \count($ligne->getDessertes()->filter(fn (Desserte $d) => $d->getTronconDessertes()->count() > 0)) > 0;
    }

    /**
     * @param array<string, string[]> $adjacence
     * @param array<string, int>      $duree
     * @param array<string, Desserte> $dessertesParZdc
     */
    private function construireArbreSimple(SymfonyStyle $io, string $label, array $adjacence, array $duree, array $dessertesParZdc): void
    {
        $ligne = $this->trouverLigne($label);
        if (null === $ligne || $this->dejaConstruite($ligne)) {
            $io->warning("Ligne $label deja construite ou introuvable, ignoree.");

            return;
        }
        if ([] === $adjacence) {
            $io->warning("Ligne $label : aucune donnee de graphe, ignoree.");

            return;
        }

        $zdcRacine = $this->trouverUnTerminus($adjacence);
        $directionsParZdc = [$zdcRacine => $this->creerDirection($ligne, $dessertesParZdc[$zdcRacine])];
        $avant = $this->nbTronconsCrees;
        $this->descendre($ligne, $adjacence, $duree, $dessertesParZdc, $zdcRacine, null, 0, $directionsParZdc[$zdcRacine], [], $directionsParZdc);

        $io->writeln("Ligne $label : ".\count($dessertesParZdc).' stations, '.($this->nbTronconsCrees - $avant).' troncons.');
    }

    private function trouverUnTerminus(array $adjacence): string
    {
        foreach ($adjacence as $zdc => $voisins) {
            if (1 === \count(array_unique($voisins))) {
                return $zdc;
            }
        }

        throw new \RuntimeException('Aucun terminus (station de degre 1) trouve dans ce graphe.');
    }

    /**
     * Descend recursivement l'arbre depuis $zdcCourant, creant un Troncon + des Mission pour
     * chaque arete, une Direction par terminus reel (station de degre 1, ou limite volontaire).
     * $dirRacine est reutilisee pour TOUTES les missions "vers la racine", quelle que soit la
     * profondeur ou la branche : un train qui revient vers la racine porte cette meme direction
     * partout (comme pour les trams en Y, generalise a une profondeur arbitraire).
     *
     * @param array<string, string[]>   $adjacence
     * @param array<string, int>        $duree
     * @param array<string, Desserte>   $dessertesParZdc
     * @param string[]                  $limites          ZdCId ou s'arreter (traite comme un terminus meme si degre > 1)
     * @param array<string, Direction>  $directionsParZdc memoisation par reference : zdc terminus deja rencontre => sa Direction
     *
     * @return Direction[] les Direction-terminus trouvees dans CE sous-arbre (zdcCourant inclus s'il en est une)
     */
    private function descendre(
        Ligne $ligne,
        array $adjacence,
        array $duree,
        array $dessertesParZdc,
        string $zdcCourant,
        ?string $zdcParent,
        int $numeroParent,
        Direction $dirRacine,
        array $limites,
        array &$directionsParZdc,
    ): array {
        $estLimite = isset($limites[$zdcCourant]) && null !== $zdcParent;
        $voisins = $estLimite ? [] : array_unique(array_filter($adjacence[$zdcCourant] ?? [], fn (string $v) => $v !== $zdcParent));

        if ([] === $voisins) {
            return [$directionsParZdc[$zdcCourant]];
        }

        $directionsSousArbre = [];
        foreach ($voisins as $zdcEnfant) {
            $numero = $numeroParent + 1;

            if (!isset($directionsParZdc[$zdcEnfant])) {
                // Terminus reel = plus aucun voisin une fois le parent (zdcCourant) exclu (degre
                // total 1, donc 0 voisin restant) — pas 1, qui signifierait au contraire "encore
                // un troncon a parcourir avant la fin", et laisserait la Direction non creee.
                $estTerminusEnfant = isset($limites[$zdcEnfant])
                    || 0 === \count(array_unique(array_filter($adjacence[$zdcEnfant] ?? [], fn (string $v) => $v !== $zdcCourant)));
                if ($estTerminusEnfant) {
                    $directionsParZdc[$zdcEnfant] = $this->creerDirection($ligne, $dessertesParZdc[$zdcEnfant]);
                }
            }

            $tronconDureeSecondes = $duree[$zdcCourant.'|'.$zdcEnfant] ?? null;
            $troncon = $this->creerTronconBidirectionnel($dessertesParZdc[$zdcCourant], $dessertesParZdc[$zdcEnfant], $tronconDureeSecondes);
            ++$this->nbTronconsCrees;

            $sousDirections = $this->descendre($ligne, $adjacence, $duree, $dessertesParZdc, $zdcEnfant, $zdcCourant, $numero, $dirRacine, $limites, $directionsParZdc);

            foreach ($sousDirections as $dir) {
                $this->creerMission($troncon, $dessertesParZdc[$zdcEnfant], $dir, $numero);
            }
            $this->creerMission($troncon, $dessertesParZdc[$zdcCourant], $dirRacine, $numero);

            $directionsSousArbre = array_merge($directionsSousArbre, $sousDirections);
        }

        return $directionsSousArbre;
    }

    /**
     * Construit la ligne D en deux arbres separes (tronc Creil<->Villeneuve-Saint-Georges et
     * branche Corbeil-Essonnes<->Malesherbes), le maillage Evry/Corbeil/Juvisy entre les deux
     * restant volontairement non construit (voir TODO.md).
     *
     * @param array<string, string[]> $adjacence
     * @param array<string, int>      $duree
     * @param array<string, Desserte> $dessertesParZdc
     */
    private function construireRerD(SymfonyStyle $io, array $adjacence, array $duree, array $dessertesParZdc): void
    {
        $ligne = $this->trouverLigne('D');
        if (null === $ligne || $this->dejaConstruite($ligne)) {
            $io->warning('Ligne D deja construite ou introuvable, ignoree.');

            return;
        }
        if ([] === $adjacence) {
            $io->warning('Ligne D : aucune donnee de graphe, ignoree.');

            return;
        }

        $avant = $this->nbTronconsCrees;

        // Racine 1 : Creil, s'arrete a Villeneuve-Saint-Georges (limite du maillage).
        $zdcCreil = $this->trouverZdcParNom($dessertesParZdc, 'Creil');
        $directionsParZdc = [$zdcCreil => $this->creerDirection($ligne, $dessertesParZdc[$zdcCreil])];
        $this->descendre($ligne, $adjacence, $duree, $dessertesParZdc, $zdcCreil, null, 0, $directionsParZdc[$zdcCreil], self::D_LIMITES, $directionsParZdc);
        $nb1 = $this->nbTronconsCrees - $avant;

        // Racine 2 : Malesherbes, s'arrete a Corbeil-Essonnes (limite du maillage).
        $zdcMalesherbes = $this->trouverZdcParNom($dessertesParZdc, 'Malesherbes');
        $directionsParZdc2 = [$zdcMalesherbes => $this->creerDirection($ligne, $dessertesParZdc[$zdcMalesherbes])];
        $this->descendre($ligne, $adjacence, $duree, $dessertesParZdc, $zdcMalesherbes, null, 0, $directionsParZdc2[$zdcMalesherbes], self::D_LIMITES, $directionsParZdc2);
        $nb2 = $this->nbTronconsCrees - $avant - $nb1;

        $io->writeln('Ligne D : '.\count($dessertesParZdc)." stations connues, $nb1 troncons (tronc Creil) + $nb2 troncons (branche Malesherbes). Maillage Evry/Corbeil/Juvisy non construit (voir TODO.md).");
    }

    /**
     * @param array<string, Desserte> $dessertesParZdc
     */
    private function trouverZdcParNom(array $dessertesParZdc, string $nom): string
    {
        foreach ($dessertesParZdc as $zdc => $desserte) {
            if ($desserte->getStation()?->getLabel() === $nom) {
                return $zdc;
            }
        }

        throw new \RuntimeException(sprintf('Station "%s" introuvable.', $nom));
    }

    private function creerTronconBidirectionnel(Desserte $a, Desserte $b, ?int $dureeSecondes): Troncon
    {
        $troncon = new Troncon();
        $troncon->setTypeTroncon($this->exterieur);
        $troncon->setDureeReelleSecondes($dureeSecondes);
        $this->entityManager->persist($troncon);

        $this->creerTronconDesserte($troncon, $a, $this->depart);
        $this->creerTronconDesserte($troncon, $b, $this->arrivee);
        $this->creerTronconDesserte($troncon, $b, $this->depart);
        $this->creerTronconDesserte($troncon, $a, $this->arrivee);

        return $troncon;
    }

    private function creerTronconDesserte(Troncon $troncon, Desserte $desserte, TypeDesserte $role): void
    {
        $tronconDesserte = new TronconDesserte();
        $tronconDesserte->setTroncon($troncon);
        $tronconDesserte->setDesserte($desserte);
        $tronconDesserte->setTypeDesserte($role);
        $this->entityManager->persist($tronconDesserte);

        if ($role === $this->depart) {
            $this->departTdCache[spl_object_id($troncon).'|'.spl_object_id($desserte)] = $tronconDesserte;
        }
    }

    private function creerDirection(Ligne $ligne, Desserte $desserteTerminus): Direction
    {
        $direction = new Direction();
        $direction->setLigne($ligne);
        $direction->setDesserteTerminus($desserteTerminus);
        $this->entityManager->persist($direction);

        return $direction;
    }

    private function creerMission(Troncon $troncon, Desserte $desserteDepart, Direction $direction, int $numero): void
    {
        $tronconDesserteDepart = $this->departTdCache[spl_object_id($troncon).'|'.spl_object_id($desserteDepart)] ?? null;

        $mission = new Mission();
        $mission->setTronconDesserte($tronconDesserteDepart);
        $mission->setDirection($direction);
        $mission->setService($this->serviceUnique);
        $mission->setNumero($numero);
        $this->entityManager->persist($mission);
    }
}
