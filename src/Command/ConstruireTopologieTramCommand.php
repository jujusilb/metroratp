<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Direction;
use App\Entity\Ligne;
use App\Entity\Materiel;
use App\Entity\MaterielLigne;
use App\Entity\Mission;
use App\Entity\Service;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use App\Entity\TypeTroncon;
use App\Repository\LigneRepository;
use App\Repository\MaterielRepository;
use App\Repository\ServiceRepository;
use App\Repository\TypeDesserteRepository;
use App\Repository\TypeMaterielRepository;
use App\Repository\TypeTronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Construit les troncons/directions/missions des 15 lignes de tramway (T1-T14), dont les
 * lignes/stations/dessertes existent deja (app:importer-reseau-complet). Topologie extraite du
 * GTFS complet (documentation/scripts/extraire_trams.py, noms de station recales sur le
 * referentiel "zone de correspondance" IDFM pour matcher exactement les Station deja en base) et
 * verifiee contre les plans officiels RATP (documentation/PLAN/PDF/TRAMWAY) : T4 et T8 sont de
 * vraies lignes en Y (tronc commun + deux branches), toutes les autres sont simples et lineaires.
 *
 * A usage unique : refuse de s'executer si une ligne a deja des troncons.
 */
#[AsCommand(name: 'app:construire-topologie-tram', description: 'Construit les troncons/missions des 15 lignes de tramway')]
class ConstruireTopologieTramCommand extends Command
{
    /** @var array<string, array<int, string>> lignes simples : sequence unique de stations */
    private const LIGNES_SIMPLES = [
        'T1' => ['Saint-Denis', 'Théâtre Gérard Philipe', 'Marché de Saint-Denis', 'Basilique de Saint-Denis / Cité Langevin', 'Cimetière de Saint-Denis', 'Hôpital Delafontaine', 'Cosmonautes', 'La Courneuve - Six Routes', 'Hôtel de Ville de la Courneuve', 'Stade Géo André', 'Danton', 'La Courneuve - 8 Mai 1945', 'Maurice Lachâtre', 'Drancy - Avenir', 'Hôpital Avicenne', 'Gaston Roulaud', 'Escadrille Normandie - Niémen', 'La Ferme', 'Libération', 'Hôtel de Ville de Bobigny', 'Bobigny - Pablo Picasso'],
        'T2' => ['Porte de Versailles', "Porte d'Issy", 'Suzanne Lenglen', 'Henri Farman', 'Issy Val de Seine', 'Jacques-Henri Lartigue', 'Les Moulineaux', 'Meudon-sur-Seine', 'Brimborion', 'Musée de Sèvres', 'Parc de Saint-Cloud', 'Les Milons', 'Les Coteaux', 'Suresnes Longchamp', 'Belvédère', 'Puteaux', 'La Défense', "Faubourg de l'Arche", 'Les Fauvelles', 'Charlebourg', 'Jacqueline Auriol', 'Victor Basch', 'Parc Pierre Lagravère', 'Pont de Bezons'],
        'T3a' => ['Porte de Vincennes', 'Alexandra David-Néel', 'Montempoivre', 'Porte Dorée', 'Porte de Charenton', 'Baron Le Roy', 'Avenue de France', 'Maryse Bastié', "Porte d'Ivry", 'Porte de Choisy', "Porte d'Italie", 'Poterne des Peupliers', 'Stade Charléty - Porte de Gentilly', 'Cité Universitaire', 'Montsouris', "Porte d'Orléans", 'Jean Moulin', 'Didot', 'Porte de Vanves', 'Brancion', 'Georges Brassens', 'Porte de Versailles', 'Desnouettes', 'Balard', 'Pont du Garigliano - Hôpital Européen G. Pompidou'],
        'T3b' => ['Porte Dauphine', 'Anna de Noailles', 'Neuilly - Porte Maillot', 'Anny Flore', 'Thérèse Pierre', 'Porte de Champerret', 'Square Sainte-Odile', 'Marguerite Long', 'Porte de Clichy', 'Honoré De Balzac', 'Epinettes - Pouchet', 'Porte de Saint-Ouen', 'Angélique Compoint - Porte de Montmartre', 'Porte de Clignancourt', 'Diane Arbus - Porte des Poissonniers', 'Porte de la Chapelle', 'Colette Besson', "Porte d'Aubervilliers", 'Rosa Parks', 'Canal Saint-Denis', 'Porte de la Villette', 'Ella Fitzgerald', 'Delphine Seyrig', 'Porte de Pantin', 'Butte du Chapeau Rouge', 'Hôpital Robert Debré', 'Porte des Lilas', 'Adrienne Bolland', 'Séverine', 'Porte de Bagnolet', 'Marie De Miribel', 'Porte de Montreuil', 'Porte de Vincennes'],
        'T5' => ['Marché de Saint-Denis', 'Baudelaire', 'Roger Semât', 'Guynemer', 'Petit Pierrefitte', 'Joncherolles', 'Suzanne Valadon', 'Mairie de Pierrefitte', "Alcide d'Orbigny", 'Jacques Prévert', 'Butte Pinson (Parc Régional)', 'Les Cholettes', 'Les Flanades', 'Paul Valéry', 'Lochères', 'Garges - Sarcelles'],
        'T6' => ['Châtillon - Montrouge', 'Vauban', 'Centre de Châtillon', 'Parc André Malraux', 'Division Leclerc', 'Soleil Levant', 'Hôpital Béclère', 'Mail de la Plaine', 'Pavé Blanc', 'Georges Pompidou', 'Georges Millandy', 'Meudon-la-Forêt', 'Vélizy 2', 'Dewoitine', 'Inovel Parc Nord', 'Louvois', 'Mairie de Vélizy', "L'Onde (Maison des Arts)", 'Robert Wagner', 'Viroflay Rive Gauche', 'Viroflay Rive Droite'],
        'T7' => ['Villejuif - Louis Aragon', 'Lamartine', 'Domaine Chérioux', 'Moulin Vert', 'Bretagne', 'Auguste Perret (Cimetière Parisien)', 'Chevilly-Larue (Marché International)', 'La Belle Epine', 'Place de la Logistique', 'Porte de Rungis', 'Saarinen', 'Robert Schuman (Parc Silic Centre)', 'Rungis la Fraternelle', 'Hélène Boucher (Orlytech)', 'Caroline Aigle (Orlyfret)', "Coeur d'Orly", 'Aéroport d’Orly (Terminal 4)', "Porte de l'Essonne"],
        'T9' => ['Orly - Gaston Viens', 'Les Saules', 'Christophe Colomb', 'Four - Peary', 'Carle - Darthé', 'Rouget de Lisle', 'Verdun - Hoche', 'Trois Communes', 'Watteau - Rondenay', 'Constant Coquelin', 'Camille Groult', 'Mairie de Vitry-sur-Seine', 'Musée MAC VAL', 'Beethoven - Concorde', 'Germaine Tailleferre', 'La Briqueterie', "Cimetière Parisien d'Ivry", 'Châteaudun - Barbès', 'Porte de Choisy'],
        'T10' => ['Jardin Parisien', 'Hôpital Béclère', 'Le Hameau', 'Parc des Sports', 'Noveos', 'Malabry', 'Vallée aux Loups', 'Cité-Jardin', 'Les Peintres', 'Théâtre La Piscine', 'Petit-Châtenay', 'LaVallée', 'La Croix de Berny'],
        'T11' => ['Épinay-sur-Seine', 'Épinay - Villetaneuse', 'Villetaneuse Université', 'Pierrefitte - Stains', 'Stains la Cerisaie', 'Dugny - La Courneuve', 'Le Bourget'],
        'T12' => ['Évry - Courcouronnes', 'Bois Briard', 'Traité de Rome', 'Bois de Saint-Eutrope', 'Ferme Neuve', 'Amédée Gordini', "Coteaux de l'Orge", 'Parc du Château', 'Épinay-sur-Orge', 'Petit Vaux', 'Gravigny Balizy', 'Chilly-Mazarin', 'Longjumeau', 'Champlan', 'Massy Europe', 'Massy - Palaiseau'],
        'T13' => ['Saint-Germain-en-Laye', 'Camp des Loges', 'Lisière Pereire', 'Fourqueux - Bel Air', 'Mareil-Marly', "L'Etang - Les Sablons", 'Saint-Nom-la-Bretèche Forêt de Marly', 'Noisy-le-Roi', 'Bailly', 'Allée Royale', 'Les Portes de Saint-Cyr', 'Saint-Cyr'],
        'T14' => ['Esbly', 'Montry - Condé', 'Couilly - Saint-Germain - Quincy', 'Villiers Montbarbin', 'Crécy-la-Chapelle'],
    ];

    /**
     * Lignes en Y : tronc commun (en 1er) + 2 branches (chacune debutant par la station de
     * bifurcation, deja incluse dans le tronc). Verifie contre les plans officiels RATP.
     */
    private const LIGNES_EN_Y = [
        'T4' => [
            'tronc' => ['Bondy', 'Remise à Jorelle', 'Les Coquetiers', 'Allée de la Tour-Rendez-vous', 'Les Pavillons-sous-Bois', 'Gargan'],
            'brancheA' => ['Gargan', 'Lycée Henri Sellier', "L'Abbaye", 'Freinville Sevran', 'Rougemont Chanteloup', 'Aulnay-sous-Bois'],
            'brancheB' => ['Gargan', 'République - Marx Dormoy', 'Léon Blum', 'Maurice Audin', 'Clichy-sous-Bois - Mairie', 'Romain Rolland', 'Clichy - Montfermeil', 'Notre-Dame-des-Anges', 'Arboretum', 'Hôpital de Montfermeil'],
        ],
        'T8' => [
            'tronc' => ['Saint-Denis - Porte de Paris', 'Pierre de Geyter', 'Saint-Denis', 'Paul Éluard', 'Delaunay - Belleville'],
            'brancheA' => ['Delaunay - Belleville', 'Blumenthal', 'Les Mobiles', 'Les Béatus', 'Rose Bertin', 'Lacépède', 'Gilbert Bonnemaison', 'Épinay-sur-Seine', 'Épinay - Orgemont'],
            'brancheB' => ['Delaunay - Belleville', 'César', 'Jean Vilar', 'Pablo Neruda', 'Villetaneuse Université'],
        ],
    ];

    /**
     * codeExterne (route_id IDFM) reel de chaque ligne, indispensable pour la retrouver sans
     * ambiguite : le label seul ne suffit pas, ex. il existe aussi une ligne de BUS de
     * remplacement labellisee "T1" (code C02404) en plus du vrai tramway (C01389).
     */
    private const LIGNES_CODES = [
        'T1' => 'C01389', 'T2' => 'C01390', 'T3a' => 'C01391', 'T3b' => 'C01679',
        'T4' => 'C01843', 'T5' => 'C01684', 'T6' => 'C01794', 'T7' => 'C01774',
        'T8' => 'C01795', 'T9' => 'C02317', 'T10' => 'C02528', 'T11' => 'C01999',
        'T12' => 'C02529', 'T13' => 'C02344', 'T14' => 'C02732',
    ];

    private TypeDesserte $depart;
    private TypeDesserte $arrivee;
    private TypeTroncon $exterieur;
    private Service $serviceUnique;

    /** @var array<int, TronconDesserte> cle = spl_object_id(troncon).'|'.spl_object_id(desserte) pour role Depart */
    private array $departTdCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LigneRepository $ligneRepository,
        private readonly TypeDesserteRepository $typeDesserteRepository,
        private readonly TypeTronconRepository $typeTronconRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly MaterielRepository $materielRepository,
        private readonly TypeMaterielRepository $typeMaterielRepository,
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

        foreach (self::LIGNES_SIMPLES as $label => $stations) {
            $ligne = $this->trouverLigne($label);
            if (null === $ligne) {
                $io->error("Ligne $label introuvable.");

                return Command::FAILURE;
            }
            if ($this->dejaConstruite($ligne)) {
                $io->warning("Ligne $label a deja des troncons, ignoree.");
                continue;
            }

            $dessertes = array_map(fn (string $nom) => $this->trouverDesserte($ligne, $nom), $stations);
            $dirVersFin = $this->creerDirection($ligne, $dessertes[array_key_last($dessertes)]);
            $dirVersDebut = $this->creerDirection($ligne, $dessertes[0]);

            $n = \count($dessertes) - 1;
            for ($i = 0; $i < $n; ++$i) {
                $numero = $i + 1;
                $troncon = $this->creerTronconBidirectionnel($dessertes[$i], $dessertes[$i + 1]);
                $this->creerMission($troncon, $dessertes[$i + 1], $dirVersDebut, $numero);
                $this->creerMission($troncon, $dessertes[$i], $dirVersFin, $numero);
            }

            $io->writeln("Ligne $label : ".\count($dessertes)." stations, $n troncons.");
        }

        foreach (self::LIGNES_EN_Y as $label => $topologie) {
            $this->construireLigneEnY($io, $label, $topologie);
        }

        $this->ajouterMaterielTram();

        $this->entityManager->flush();
        $io->success('Topologie des 15 lignes de tramway construite.');

        return Command::SUCCESS;
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
     * Construit une ligne en Y : tronc commun (partage par les 2 branches, meme numerotation
     * pour les deux directions) puis chaque branche continue independamment.
     *
     * @param array{tronc: string[], brancheA: string[], brancheB: string[]} $topologie
     */
    private function construireLigneEnY(SymfonyStyle $io, string $label, array $topologie): void
    {
        $ligne = $this->trouverLigne($label);
        if (null === $ligne || $this->dejaConstruite($ligne)) {
            $io->warning("Ligne $label deja construite ou introuvable, ignoree.");

            return;
        }

        $dTronc = array_map(fn (string $n) => $this->trouverDesserte($ligne, $n), $topologie['tronc']);
        $dA = array_map(fn (string $n) => $this->trouverDesserte($ligne, $n), $topologie['brancheA']);
        $dB = array_map(fn (string $n) => $this->trouverDesserte($ligne, $n), $topologie['brancheB']);

        $dirA = $this->creerDirection($ligne, $dA[array_key_last($dA)]);
        $dirB = $this->creerDirection($ligne, $dB[array_key_last($dB)]);
        $dirTronc = $this->creerDirection($ligne, $dTronc[0]);

        $numero = 0;
        for ($i = 0, $n = \count($dTronc) - 1; $i < $n; ++$i) {
            ++$numero;
            $troncon = $this->creerTronconBidirectionnel($dTronc[$i], $dTronc[$i + 1]);
            $this->creerMission($troncon, $dTronc[$i + 1], $dirTronc, $numero);
            $this->creerMission($troncon, $dTronc[$i], $dirA, $numero);
            $this->creerMission($troncon, $dTronc[$i], $dirB, $numero);
        }

        $numeroA = $numero;
        for ($i = 0, $n = \count($dA) - 1; $i < $n; ++$i) {
            ++$numeroA;
            $troncon = $this->creerTronconBidirectionnel($dA[$i], $dA[$i + 1]);
            $this->creerMission($troncon, $dA[$i + 1], $dirTronc, $numeroA);
            $this->creerMission($troncon, $dA[$i], $dirA, $numeroA);
        }

        $numeroB = $numero;
        for ($i = 0, $n = \count($dB) - 1; $i < $n; ++$i) {
            ++$numeroB;
            $troncon = $this->creerTronconBidirectionnel($dB[$i], $dB[$i + 1]);
            $this->creerMission($troncon, $dB[$i + 1], $dirTronc, $numeroB);
            $this->creerMission($troncon, $dB[$i], $dirB, $numeroB);
        }

        $io->writeln("Ligne $label : construite en Y (tronc + 2 branches).");
    }

    private function trouverDesserte(Ligne $ligne, string $nomStation): Desserte
    {
        foreach ($ligne->getDessertes() as $desserte) {
            if ($desserte->getStation()?->getLabel() === $nomStation) {
                return $desserte;
            }
        }

        throw new \RuntimeException(sprintf('Desserte "%s" introuvable sur la ligne %s.', $nomStation, $ligne->getLabel()));
    }

    private function creerTronconBidirectionnel(Desserte $a, Desserte $b): Troncon
    {
        $troncon = new Troncon();
        $troncon->setTypeTroncon($this->exterieur);
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

    private function ajouterMaterielTram(): void
    {
        $citadis = $this->materielRepository->findOneBy(['label' => 'Citadis']);
        if (null === $citadis) {
            $citadis = new Materiel();
            $citadis->setLabel('Citadis');
            $citadis->setTypeMateriel($this->typeMaterielRepository->findOneBy(['label' => 'ferraille']));
            $this->entityManager->persist($citadis);
        }

        foreach (array_keys(self::LIGNES_CODES) as $label) {
            $ligne = $this->trouverLigne($label);
            if (null === $ligne) {
                continue;
            }
            $materielLigne = new MaterielLigne();
            $materielLigne->setLigne($ligne);
            $materielLigne->setMateriel($citadis);
            $this->entityManager->persist($materielLigne);
        }
    }
}
