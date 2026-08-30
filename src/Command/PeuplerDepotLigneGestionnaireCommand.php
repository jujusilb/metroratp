<?php

namespace App\Command;

use App\Entity\Depot;
use App\Entity\DepotGestionnaire;
use App\Entity\DepotLigne;
use App\Entity\Gestionnaire;
use App\Entity\Ligne;
use App\Entity\Materiel;
use App\Entity\MaterielDepot;
use App\Entity\MaterielLigne;
use App\Repository\DepotGestionnaireRepository;
use App\Repository\DepotLigneRepository;
use App\Repository\DepotRepository;
use App\Repository\GestionnaireRepository;
use App\Repository\MaterielDepotRepository;
use App\Repository\MaterielLigneRepository;
use App\Repository\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Peuple DepotLigne, DepotGestionnaire et MaterielDepot (jusqu'ici vides malgre 111 Depot et 52
 * Materiel deja en base). Deux sources, de fiabilite differente (best-effort assume, voir
 * TODO.md) :
 *
 * 1. RATP (~40 des 111 Depot) : DONNEES_RATP vient du champ "depot_nom"/"materiel_roulant" de
 *    l'infobox {{Ligne de transport en commun}} de chaque ligne, depouille depuis
 *    https://fr.wikipedia.org/wiki/Lignes_de_bus_RATP_de_20_%C3%A0_99 (et pages soeurs 100-199,
 *    200-299, 300-399, "speciales"). Couverture partielle assumee : les lignes 400+, Noctilien et
 *    les lignes RATP de banlieue documentees via des pages "Reseau de bus <secteur>" dediees
 *    (structure differente, non depouillee ici) n'y figurent pas. RATP_DEPOT_NOMS_CONNUS (liste
 *    "Repartition energetique des centres bus" de https://fr.wikipedia.org/wiki/Centre_bus_RATP)
 *    complete ensuite l'identification des Depot RATP dont aucune ligne n'a ete depouillee
 *    ci-dessus (ex: Point-du-Jour, Nanterre, Massy...) : DepotGestionnaire leur est assigne, mais
 *    pas DepotLigne (donnee absente).
 *
 * 2. Tout Depot non identifie RATP par la source 1 (les Depot de banlieue geres par un operateur
 *    Optile) : DepotGestionnaire et DepotLigne sont deduits de donnees deja en base plutot que
 *    d'une recherche web ligne par ligne (impraticable pour ~70 depots) - le gestionnaire
 *    dominant (le plus de lignes de Bus desservant une Station de la Ville du Depot) est calcule,
 *    en s'appuyant sur le fait qu'Ile-de-France Mobilites attribue les reseaux de bus Optile par
 *    lot geographique exclusif (verifie : "Keolis Argenteuil Boucles de Seine" domine tres
 *    largement a Argenteuil, etc.). Approximation assumee : un Depot peut ne pas etre l'unique
 *    depot de son operateur (toutes les lignes du gestionnaire dominant SUR CETTE VILLE lui sont
 *    alors attribuees, meme si un autre Depot du meme operateur existe ailleurs).
 *
 * MaterielDepot n'est peuple QUE pour les Depot RATP identifies par la source 1 (Materiel/
 * MaterielLigne reels, tires de la meme infobox Wikipedia) : aucune donnee de materiel roulant
 * fiable n'a ete trouvee pour les operateurs Optile ligne par ligne, un remplissage generique
 * aurait ete invente plutot que documente - laisse vide (trou assume) plutot que fabrique.
 *
 * Idempotent : verifie l'existence de chaque ligne de jointure avant de la creer.
 */
#[AsCommand(name: 'app:peupler-depot-ligne-gestionnaire', description: 'Peuple DepotLigne, DepotGestionnaire et MaterielDepot (RATP via Wikipedia, Optile via gestionnaire dominant par ville)')]
class PeuplerDepotLigneGestionnaireCommand extends Command
{
    /**
     * @var array<int, array{ligne: string, depot: string, materiels: string[]}>
     */
    private const DONNEES_RATP = [
        ['ligne' => '20', 'depot' => 'Lagny', 'materiels' => ['Bluebus 12']],
        ['ligne' => '21', 'depot' => 'Corentin', 'materiels' => ['Urbanway 12 Hybride', 'GX 337 ELEC', 'Bluebus 12 Facelift']],
        ['ligne' => '24', 'depot' => 'Lebrun', 'materiels' => ['GX 337 ELEC']],
        ['ligne' => '26', 'depot' => 'Belliard', 'materiels' => ['GX 437 Hybride', 'Urbanway 18 Hybride']],
        ['ligne' => '28', 'depot' => 'Corentin', 'materiels' => ['GX 337 ELEC', 'Urbanway 12 Hybride']],
        ['ligne' => '29', 'depot' => 'Lagny', 'materiels' => ['Bluebus 12', 'Bluebus 12 Facelift']],
        ['ligne' => '30', 'depot' => 'Croix-Nivert', 'materiels' => ['GX 337 ELEC', 'Citelis 12']],
        ['ligne' => '38', 'depot' => 'Belliard', 'materiels' => ['GX 437 Hybride', 'Urbanway 18 Hybride', "Lion's City"]],
        ['ligne' => '39', 'depot' => 'Croix-Nivert', 'materiels' => ['Urbanway 12 Hybride']],
        ['ligne' => '46', 'depot' => 'Lagny', 'materiels' => ['Bluebus 12']],
        ['ligne' => '48', 'depot' => 'Les Lilas', 'materiels' => ['Urbanway 12 Hybride', 'GX 337 ELEC']],
        ['ligne' => '56', 'depot' => 'Lagny', 'materiels' => ['Aptis']],
        ['ligne' => '57', 'depot' => 'Lagny', 'materiels' => ['Bluebus 12']],
        ['ligne' => '58', 'depot' => 'Malakoff', 'materiels' => ['Citelis 12', 'GX 337 SE']],
        ['ligne' => '59', 'depot' => 'Corentin', 'materiels' => ['Urbanway 12 Hybride', 'Bluebus SE', 'GX 337 SE', 'Bluebus 12 Facelift']],
        ['ligne' => '60', 'depot' => 'Belliard', 'materiels' => ["Lion's City", "Lion's City Hybride"]],
        ['ligne' => '63', 'depot' => 'Lebrun', 'materiels' => ['GX 337 ELEC']],
        ['ligne' => '64', 'depot' => 'Lagny', 'materiels' => ['Aptis']],
        ['ligne' => '67', 'depot' => 'Corentin', 'materiels' => ['GX 337 ELEC', 'Urbanway 12 Hybride']],
        ['ligne' => '68', 'depot' => 'Malakoff', 'materiels' => ['GX 337 ELEC', 'Irizar ie 12']],
        ['ligne' => '69', 'depot' => 'Lagny', 'materiels' => ['Bluebus 12', 'Bluebus 12 Facelift']],
        ['ligne' => '70', 'depot' => 'Croix-Nivert', 'materiels' => ['GX 337 ELEC', 'Citelis 12', 'Urbanway 12 Hybride']],
        ['ligne' => '76', 'depot' => 'Les Lilas', 'materiels' => ['GX 337 SE']],
        ['ligne' => '77', 'depot' => 'Lagny', 'materiels' => ['Bluebus 12 Facelift']],
        ['ligne' => '80', 'depot' => 'Belliard', 'materiels' => ['GX 437 Hybride', 'Urbanway 18 Hybride']],
        ['ligne' => '82', 'depot' => 'Croix-Nivert', 'materiels' => ['Urbanway 12 Hybride']],
        ['ligne' => '84', 'depot' => 'Lebrun', 'materiels' => ['GX 337 ELEC']],
        ['ligne' => '86', 'depot' => 'Lagny', 'materiels' => ['Urbanway 12 Hybride', 'Bluebus 12', 'Bluebus 12 Facelift']],
        ['ligne' => '87', 'depot' => 'Lagny', 'materiels' => ['Aptis']],
        ['ligne' => '88', 'depot' => 'Corentin', 'materiels' => ['Bluebus 12']],
        ['ligne' => '92', 'depot' => 'Corentin', 'materiels' => ['GX 337 ELEC', 'Urbanway 12 Hybride']],
        ['ligne' => '94', 'depot' => 'Corentin', 'materiels' => ['GX 337 ELEC', 'Urbanway 12 Hybride', 'Bluebus 12 Facelift']],
        ['ligne' => '96', 'depot' => 'Les Lilas', 'materiels' => ['GX 337 ELEC']],
        // "98" est le numero d'ancre Wikipedia (position dans la page), pas le vrai label : cette
        // ligne s'appelle reellement "PC" (Petite Ceinture, {{ancre|Ligne 98|Ligne PC}} et
        // ligne_nom = PC dans l'infobox) - c'est bien sous ce label qu'elle existe deja en base.
        ['ligne' => 'PC', 'depot' => 'Belliard', 'materiels' => ["Lion's City Hybride"]],
        ['ligne' => '102', 'depot' => 'Les Lilas', 'materiels' => ['Urbanway 12', 'Bluebus 12 Facelift']],
        ['ligne' => '109', 'depot' => 'Créteil', 'materiels' => ["Lion's City GNV"]],
        ['ligne' => '115', 'depot' => 'Les Lilas', 'materiels' => ['GX 337 ELEC', 'Bluebus 12 Facelift']],
        ['ligne' => '121', 'depot' => 'Les Lilas', 'materiels' => ['GX 337 ELEC']],
        ['ligne' => '122', 'depot' => 'Les Lilas', 'materiels' => ['Urbanway 12 Hybride', 'Bluebus 12 Facelift']],
        ['ligne' => '123', 'depot' => 'Malakoff', 'materiels' => ["Lion's City Hybride"]],
        ['ligne' => '126', 'depot' => 'Malakoff', 'materiels' => ['GX 337 Hybride']],
        ['ligne' => '128', 'depot' => 'Malakoff', 'materiels' => ['Irizar ie 12']],
        ['ligne' => '129', 'depot' => 'Les Lilas', 'materiels' => ['GX 337 ELEC']],
        ['ligne' => '169', 'depot' => 'Croix-Nivert', 'materiels' => ['Citelis 12']],
        ['ligne' => '171', 'depot' => 'Croix-Nivert', 'materiels' => ['Urbanway 12 Hybride']],
        ['ligne' => '188', 'depot' => 'Corentin', 'materiels' => ['GX 337 ELEC']],
        ['ligne' => '202', 'depot' => 'Les Lilas', 'materiels' => ['Bluebus 12 Facelift']],
        ['ligne' => '215', 'depot' => 'Lagny', 'materiels' => ['Urbanway 12 Hybride']],
        ['ligne' => '245', 'depot' => 'Les Lilas', 'materiels' => ['Bluebus 12 Facelift']],
        ['ligne' => '260', 'depot' => 'Croix-Nivert', 'materiels' => ['Urbanway 12 Hybride']],
        ['ligne' => '318', 'depot' => 'Les Lilas', 'materiels' => ['Urbanway 12 Hybride']],
        ['ligne' => '322', 'depot' => 'Les Lilas', 'materiels' => ['Urbanway 12']],
        ['ligne' => '341', 'depot' => 'Belliard', 'materiels' => ["Lion's City Hybride"]],
        ['ligne' => '388', 'depot' => 'Malakoff', 'materiels' => ['Irizar ie 12']],
        // OrlyBus (283) ferme le 3 mars 2025 et RoissyBus (352) ferme le 1 mars 2026 (dates de
        // fermeture indiquees dans l'infobox Wikipedia elle-meme) : absentes du referentiel Ligne
        // a juste titre (plus des lignes actives), pas un trou d'import - aucun tuple pour elles.
    ];

    /**
     * Constructeur reel de chaque modele (source : article Wikipedia du modele, lie depuis
     * l'infobox de chaque ligne - meme convention que Materiel::constructeur pour le ferroviaire).
     */
    private const CONSTRUCTEUR_PAR_MATERIEL = [
        'Aptis' => 'Alstom',
        'Bluebus 12' => 'Bolloré',
        'Bluebus 12 Facelift' => 'Bolloré',
        'Bluebus SE' => 'Bolloré',
        'Citelis 12' => 'Irisbus',
        'Citelis 18' => 'Irisbus',
        'Crossway Line GNV' => 'Iveco Bus',
        'GX 337 ELEC' => 'Heuliez',
        'GX 337 Hybride' => 'Heuliez',
        'GX 337 SE' => 'Heuliez',
        'GX 437 Hybride' => 'Heuliez',
        'Irizar ie 12' => 'Irizar',
        "Lion's City" => 'MAN',
        "Lion's City GNV" => 'MAN',
        "Lion's City Hybride" => 'MAN',
        'Urbanway 12' => 'Iveco Bus',
        'Urbanway 12 Hybride' => 'Iveco Bus',
        'Urbanway 18' => 'Iveco Bus',
        'Urbanway 18 Hybride' => 'Iveco Bus',
    ];

    /**
     * Noms des centres bus RATP ("Repartition energetique des centres bus",
     * https://fr.wikipedia.org/wiki/Centre_bus_RATP), utilises pour identifier comme RATP les
     * Depot dont aucune ligne n'est dans DONNEES_RATP ci-dessus. Compare token par token (un
     * Depot::label du type "Nanterre - Kleber" est coupe sur " - ") pour eviter les faux positifs
     * de sous-chaine (ex: ne doit pas matcher "Fontenay-aux-Roses" ni "Asnieres-sur-Seine", deux
     * communes distinctes du "Fontenay"/"Asnieres" RATP reel).
     */
    private const RATP_DEPOT_NOMS_CONNUS = [
        'Asnières', 'Aubervilliers', 'Belliard', 'Bussy', 'Bords de Marne', 'Créteil', 'Charlebourg',
        'Corentin', 'Croix-Nivert', 'Flandre', 'Fontenay', 'Ivry', 'Lagny', 'Lebrun', 'Les Guilleraies',
        'Les Lilas', 'Les Pavillons', 'Malakoff', 'Massy', 'Mesnil-Amelot', 'Morangis', 'Nanterre',
        'Pleyel', 'Point-du-Jour', 'Saint-Denis', 'Saint-Maur', 'Thiais', 'Villiers-le-Bel', 'Vitry',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DepotRepository $depotRepository,
        private readonly MaterielRepository $materielRepository,
        private readonly GestionnaireRepository $gestionnaireRepository,
        private readonly DepotLigneRepository $depotLigneRepository,
        private readonly DepotGestionnaireRepository $depotGestionnaireRepository,
        private readonly MaterielLigneRepository $materielLigneRepository,
        private readonly MaterielDepotRepository $materielDepotRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ratp = $this->gestionnaireRepository->findOneBy(['label' => 'RATP']);
        if (null === $ratp) {
            $io->error('Gestionnaire "RATP" introuvable.');

            return Command::FAILURE;
        }

        $depotsRatpParLabel = [];
        foreach ($this->depotRepository->findAll() as $depot) {
            $depotsRatpParLabel[$depot->getLabel()] = $depot;
        }

        $io->section('1. Depots RATP identifies via Wikipedia (DepotLigne + Materiel + MaterielLigne + MaterielDepot)');
        [$depotsRatpTouches, $nbDepotLigne, $nbMateriels, $nbMaterielLigne, $nbMaterielDepot, $lignesIntrouvables]
            = $this->traiterDonneesRatp($depotsRatpParLabel, $ratp);
        $io->writeln(sprintf(
            '%d DepotLigne, %d Materiel crees, %d MaterielLigne, %d MaterielDepot crees. %d lignes introuvables en base (ignorees) : %s',
            $nbDepotLigne,
            $nbMateriels,
            $nbMaterielLigne,
            $nbMaterielDepot,
            count($lignesIntrouvables),
            implode(', ', $lignesIntrouvables) ?: 'aucune',
        ));

        $io->section('2. Depots RATP additionnels (identifies par nom, sans donnee ligne par ligne)');
        $nbGestionnaireRatp = 0;
        foreach ($this->depotRepository->findAll() as $depot) {
            if (isset($depotsRatpTouches[$depot->getId()]) || $this->estNomDepotRatpConnu($depot)) {
                $depotsRatpTouches[$depot->getId()] = $depot;
                if ($this->creerDepotGestionnaireSiAbsent($depot, $ratp)) {
                    ++$nbGestionnaireRatp;
                }
            }
        }
        $this->entityManager->flush();
        $io->writeln(sprintf('%d Depot identifies RATP au total, %d DepotGestionnaire crees a cette etape.', count($depotsRatpTouches), $nbGestionnaireRatp));

        $io->section('3. Depots Optile restants (gestionnaire dominant par Ville, deduit des donnees existantes)');
        [$nbGestionnaireOptile, $nbDepotLigneOptile, $nbSansCorrespondance] = $this->traiterDepotsOptile($depotsRatpTouches);
        $io->writeln(sprintf(
            '%d DepotGestionnaire crees, %d DepotLigne crees. %d Depot sans aucune ligne de Bus rattachable a leur Ville (trou assume).',
            $nbGestionnaireOptile,
            $nbDepotLigneOptile,
            $nbSansCorrespondance,
        ));

        $io->success('Peuplement termine.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, Depot> $depotsParLabel
     *
     * @return array{0: array<int, Depot>, 1: int, 2: int, 3: int, 4: int, 5: string[]}
     */
    private function traiterDonneesRatp(array $depotsParLabel, Gestionnaire $ratp): array
    {
        $depotsTouches = [];
        $nbDepotLigne = 0;
        $nbMateriels = 0;
        $nbMaterielLigne = 0;
        $nbMaterielDepot = 0;
        $lignesIntrouvables = [];
        $materielsParLabel = [];
        foreach ($this->materielRepository->findAll() as $materiel) {
            $materielsParLabel[$materiel->getLabel()] = $materiel;
        }

        foreach (self::DONNEES_RATP as $tuple) {
            $depot = $depotsParLabel[$tuple['depot']] ?? null;
            if (null === $depot) {
                continue;
            }

            // Filtre explicite sur typeTransport=Bus : un Ligne de Metro/RER RATP peut partager le
            // meme label numerique qu'une Ligne de Bus RATP (ex: pas de collision dans les donnees
            // actuelles, mais findOneBy() sans ce filtre serait sinon indetermine si ca arrivait).
            $ligne = $this->entityManager->createQueryBuilder()
                ->select('l')
                ->from(Ligne::class, 'l')
                ->join('l.typeTransport', 'tt')
                ->where('l.label = :label')
                ->andWhere('l.gestionnaire = :ratp')
                ->andWhere('tt.label = :bus')
                ->setParameter('label', $tuple['ligne'])
                ->setParameter('ratp', $ratp)
                ->setParameter('bus', 'Bus')
                ->getQuery()
                ->getOneOrNullResult();
            if (null === $ligne) {
                $lignesIntrouvables[] = $tuple['ligne'];
                continue;
            }

            $depotsTouches[$depot->getId()] = $depot;

            if (0 === $this->depotLigneRepository->count(['depot' => $depot, 'ligne' => $ligne])) {
                $depotLigne = new DepotLigne();
                $depotLigne->setDepot($depot);
                $depotLigne->setLigne($ligne);
                $this->entityManager->persist($depotLigne);
                ++$nbDepotLigne;
            }

            foreach ($tuple['materiels'] as $labelMateriel) {
                $materiel = $materielsParLabel[$labelMateriel] ?? null;
                if (null === $materiel) {
                    $materiel = new Materiel();
                    $materiel->setLabel($labelMateriel);
                    $materiel->setConstructeur(self::CONSTRUCTEUR_PAR_MATERIEL[$labelMateriel] ?? null);
                    $this->entityManager->persist($materiel);
                    $materielsParLabel[$labelMateriel] = $materiel;
                    ++$nbMateriels;
                }

                if (0 === $this->materielLigneRepository->count(['materiel' => $materiel, 'ligne' => $ligne])) {
                    $materielLigne = new MaterielLigne();
                    $materielLigne->setMateriel($materiel);
                    $materielLigne->setLigne($ligne);
                    $this->entityManager->persist($materielLigne);
                    ++$nbMaterielLigne;
                }

                if (0 === $this->materielDepotRepository->count(['materiel' => $materiel, 'depot' => $depot])) {
                    $materielDepot = new MaterielDepot();
                    $materielDepot->setMateriel($materiel);
                    $materielDepot->setDepot($depot);
                    $this->entityManager->persist($materielDepot);
                    ++$nbMaterielDepot;
                }
            }
        }
        $this->entityManager->flush();

        return [$depotsTouches, $nbDepotLigne, $nbMateriels, $nbMaterielLigne, $nbMaterielDepot, array_values(array_unique($lignesIntrouvables))];
    }

    private function estNomDepotRatpConnu(Depot $depot): bool
    {
        $tokens = array_map(static fn (string $t): string => mb_strtolower(trim($t), 'UTF-8'), explode(' - ', $depot->getLabel()));
        $nomsConnus = array_map(static fn (string $n): string => mb_strtolower($n, 'UTF-8'), self::RATP_DEPOT_NOMS_CONNUS);

        return [] !== array_intersect($tokens, $nomsConnus);
    }

    private function creerDepotGestionnaireSiAbsent(Depot $depot, Gestionnaire $gestionnaire): bool
    {
        if (0 !== $this->depotGestionnaireRepository->count(['depot' => $depot, 'gestionnaire' => $gestionnaire])) {
            return false;
        }

        $depotGestionnaire = new DepotGestionnaire();
        $depotGestionnaire->setDepot($depot);
        $depotGestionnaire->setGestionnaire($gestionnaire);
        $this->entityManager->persist($depotGestionnaire);

        return true;
    }

    /**
     * @param array<int, Depot> $depotsDejaTraites
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function traiterDepotsOptile(array $depotsDejaTraites): array
    {
        $nbGestionnaire = 0;
        $nbDepotLigne = 0;
        $nbSansCorrespondance = 0;

        foreach ($this->depotRepository->findAll() as $depot) {
            if (isset($depotsDejaTraites[$depot->getId()])) {
                continue;
            }

            $ville = $depot->getVille();
            if (null === $ville) {
                ++$nbSansCorrespondance;
                continue;
            }

            $dominant = $this->entityManager->createQueryBuilder()
                ->select('g', 'COUNT(DISTINCT l.id) as nbLignes')
                ->from(Gestionnaire::class, 'g')
                ->join('g.lignes', 'l')
                ->join('l.typeTransport', 'tt')
                ->join('l.dessertes', 'd')
                ->join('d.station', 's')
                ->where('s.villeRef = :ville')
                ->andWhere('tt.label = :bus')
                ->groupBy('g.id')
                ->orderBy('nbLignes', 'DESC')
                ->setMaxResults(1)
                ->setParameter('ville', $ville)
                ->setParameter('bus', 'Bus')
                ->getQuery()
                ->getOneOrNullResult();

            if (null === $dominant) {
                ++$nbSansCorrespondance;
                continue;
            }

            /** @var Gestionnaire $gestionnaire */
            $gestionnaire = $dominant[0];

            if ($this->creerDepotGestionnaireSiAbsent($depot, $gestionnaire)) {
                ++$nbGestionnaire;
            }

            $lignes = $this->entityManager->createQueryBuilder()
                ->select('DISTINCT l')
                ->from(Ligne::class, 'l')
                ->join('l.typeTransport', 'tt')
                ->join('l.dessertes', 'd')
                ->join('d.station', 's')
                ->where('s.villeRef = :ville')
                ->andWhere('tt.label = :bus')
                ->andWhere('l.gestionnaire = :gestionnaire')
                ->setParameter('ville', $ville)
                ->setParameter('bus', 'Bus')
                ->setParameter('gestionnaire', $gestionnaire)
                ->getQuery()
                ->getResult();

            foreach ($lignes as $ligne) {
                if (0 === $this->depotLigneRepository->count(['depot' => $depot, 'ligne' => $ligne])) {
                    $depotLigne = new DepotLigne();
                    $depotLigne->setDepot($depot);
                    $depotLigne->setLigne($ligne);
                    $this->entityManager->persist($depotLigne);
                    ++$nbDepotLigne;
                }
            }
        }
        $this->entityManager->flush();

        return [$nbGestionnaire, $nbDepotLigne, $nbSansCorrespondance];
    }
}
