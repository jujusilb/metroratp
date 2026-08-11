<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use App\Entity\TypeTroncon;
use App\Repository\DesserteRepository;
use App\Repository\LigneRepository;
use App\Repository\StationRepository;
use App\Repository\TypeDesserteRepository;
use App\Repository\TypeTronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Construit les troncons des lignes de bus RATP 20 a 299 (documentation/scripts/extraire_troncons_bus.py
 * pour 20-96, extraire_troncons_bus_supplement.php pour 66/74/85, extraire_troncons_bus_100_200.php
 * pour 101-199), dont les Ligne/Station/Desserte existent deja (app:importer-reseau-complet) mais
 * n'avaient aucun troncon.
 *
 * Contrairement au RER (ConstruireTopologieRerCommand), ce modele NE construit PAS de Direction/Mission :
 * ces entites ne sont utilisees nulle part pour le calcul d'itineraire (TrajetFinder ne s'appuie que
 * sur Troncon/TronconDesserte) ni ailleurs dans l'app en dehors d'un affichage decoratif optionnel
 * (Troncon::getSensCirculation() tolere deja une direction nulle). Ca permet d'eviter le probleme de
 * decomposition arbre/cycle : contrairement au RER (1 seule ligne, D, avec un maillage reel), la
 * grande majorite des lignes de bus paris­iennes ont des cycles reels dans leur graphe GTFS (rues a
 * sens unique differentes a l'aller et au retour, boucles de terminus) — voir
 * documentation/scripts/analyser_troncons_bus.py. Le graphe est donc construit tel quel, arete par
 * arete, sans exiger de structure d'arbre : Troncon/TronconDesserte represente deja un graphe general.
 *
 * Contrairement au RER, les stations de bus ont deja toutes un codeExterne (ZdCId) peuple des
 * l'import initial (pas de correspondance de nom a faire a l'execution).
 *
 * A usage unique par ligne : ignore une ligne qui a deja des troncons.
 */
#[AsCommand(name: 'app:construire-topologie-bus', description: 'Construit les troncons des lignes de bus RATP 20-199 depuis troncons_bus.csv')]
class ConstruireTopologieBusCommand extends Command
{
    private const TRONCONS_CSV = 'documentation/scripts/donnees-extraites/troncons_bus.csv';

    /** @var string[] codeExterne (route_id IDFM) de chaque ligne, voir documentation/scripts/extraire_troncons_bus.py */
    private const LIGNES_CODES = [
        '20' => 'C01072', '21' => 'C01073', '22' => 'C01074', '24' => 'C01075', '25' => 'C02243',
        '26' => 'C01076', '27' => 'C01077', '28' => 'C01078', '29' => 'C01079', '30' => 'C01080',
        '31' => 'C01081', '32' => 'C01082', '35' => 'C01680', '38' => 'C01083', '39' => 'C01084',
        '40' => 'C02254', '42' => 'C01085', '43' => 'C01086', '45' => 'C02244', '46' => 'C01087',
        '47' => 'C01088', '48' => 'C01089', '52' => 'C01090', '54' => 'C01092', '56' => 'C01093',
        '57' => 'C01094', '58' => 'C01095', '59' => 'C02245', '60' => 'C01096', '61' => 'C01097',
        '62' => 'C01098', '63' => 'C01099', '64' => 'C01100', '67' => 'C01103', '68' => 'C01104',
        '69' => 'C01105', '70' => 'C01106', '71' => 'C02246', '72' => 'C01107', '73' => 'C01108',
        '75' => 'C01110', '76' => 'C01111', '77' => 'C02251', '80' => 'C01112', '82' => 'C01114',
        '83' => 'C01115', '84' => 'C01116', '86' => 'C01118', '87' => 'C01119', '88' => 'C01120',
        '89' => 'C01121', '91' => 'C01122', '92' => 'C01123', '93' => 'C01124', '94' => 'C01125',
        '95' => 'C01126', '96' => 'C01127',
        // Decouvertes apres coup (agency_id GTFS distinct "RATP Cap Boucle Nord de Seine",
        // IDFM:1090, plutot que l'agence RATP principale IDFM:Operator_100 utilisee pour trouver
        // les codes ci-dessus) : voir extraire_troncons_bus_supplement.php.
        '66' => 'C01102', '74' => 'C01109', '85' => 'C01117',

        // Lignes RATP (+ filiales "RATP Cap ...") numerotees 101-199, voir
        // extraire_troncons_bus_100_200.php. Aucune ambiguite de numero dans cette plage
        // (contrairement a 20-100) : chaque label n'a qu'une seule Ligne RATP.
        '101' => 'C01130', '102' => 'C01131', '103' => 'C01132', '104' => 'C01133', '105' => 'C01134',
        '106' => 'C01135', '107' => 'C01136', '108' => 'C01137', '109' => 'C01138', '110' => 'C01139',
        '111' => 'C01140', '112' => 'C01141', '113' => 'C01142', '114' => 'C01143', '115' => 'C01144',
        '116' => 'C01145', '117' => 'C01146', '118' => 'C01147', '119' => 'C01148', '120' => 'C01149',
        '121' => 'C01150', '122' => 'C01151', '123' => 'C01152', '124' => 'C01153', '125' => 'C01154',
        '126' => 'C01155', '127' => 'C01156', '128' => 'C01157', '129' => 'C01158', '131' => 'C01159',
        '132' => 'C01160', '133' => 'C01161', '137' => 'C01163', '138' => 'C01164', '139' => 'C01165',
        '140' => 'C01166', '141' => 'C01167', '143' => 'C01168', '144' => 'C01169', '145' => 'C01170',
        '146' => 'C01171', '147' => 'C01172', '148' => 'C01173', '150' => 'C01174', '151' => 'C01175',
        '152' => 'C01176', '153' => 'C01177', '157' => 'C01180', '158' => 'C01181', '159' => 'C01182',
        '160' => 'C01183', '162' => 'C01184', '163' => 'C01185', '164' => 'C01186', '165' => 'C01187',
        '166' => 'C01188', '167' => 'C01189', '168' => 'C02007', '169' => 'C01190', '170' => 'C01191',
        '171' => 'C01192', '172' => 'C01193', '173' => 'C01194', '174' => 'C01195', '175' => 'C01196',
        '176' => 'C01197', '177' => 'C01198', '178' => 'C01199', '180' => 'C01201', '181' => 'C01202',
        '182' => 'C01203', '183' => 'C01204', '184' => 'C01205', '185' => 'C01206', '186' => 'C01207',
        '187' => 'C01208', '188' => 'C01209', '192' => 'C01213', '193' => 'C02288', '196' => 'C01216',
        '197' => 'C01217', '199' => 'C01218',

        // Lignes RATP (+ filiales "RATP Cap ...") numerotees 201-299, voir
        // extraire_troncons_bus_200_300.php. Exclut volontairement les lignes non-RATP de cette
        // plage (Keolis Grand Paris Vallee de la Marne, Keolis Argenteuil, Keolis Ouest
        // Val-de-Marne, ATM Croix du Sud).
        '201' => 'C01219', '202' => 'C02714', '203' => 'C01220', '208' => 'C01223', '210' => 'C01224',
        '214' => 'C01228', '215' => 'C01229', '216' => 'C01230', '217' => 'C01231', '221' => 'C01233',
        '234' => 'C01234', '235' => 'C01235', '237' => 'C01236', '238' => 'C01237', '239' => 'C01238',
        '240' => 'C02834', '241' => 'C01239', '244' => 'C01240', '245' => 'C02713', '247' => 'C01695',
        '248' => 'C01696', '249' => 'C01241', '250' => 'C01242', '251' => 'C01243', '252' => 'C01244',
        '253' => 'C01245', '254' => 'C01808', '255' => 'C01246', '256' => 'C01247', '258' => 'C01248',
        '259' => 'C02000', '260' => 'C02027', '261' => 'C01249', '263' => 'C02314', '268' => 'C01251',
        '269' => 'C01252', '270' => 'C01253', '272' => 'C01254', '274' => 'C01255', '275' => 'C01256',
        '276' => 'C01257', '277' => 'C02744', '278' => 'C01258', '281' => 'C01260', '285' => 'C01262',
        '286' => 'C01263', '292' => 'C01267', '294' => 'C01268', '297' => 'C01270', '299' => 'C01271',
    ];

    private TypeDesserte $depart;
    private TypeDesserte $arrivee;
    private TypeTroncon $exterieur;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LigneRepository $ligneRepository,
        private readonly StationRepository $stationRepository,
        private readonly DesserteRepository $desserteRepository,
        private readonly TypeDesserteRepository $typeDesserteRepository,
        private readonly TypeTronconRepository $typeTronconRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->depart = $this->typeDesserteRepository->findOneBy(['label' => 'Départ']);
        $this->arrivee = $this->typeDesserteRepository->findOneBy(['label' => 'Arrivée']);
        $this->exterieur = $this->typeTronconRepository->findOneBy(['label' => 'Exterieur']);

        /** @var array<string, Station> $stationsParCode */
        $stationsParCode = [];
        foreach ($this->stationRepository->findAll() as $station) {
            $code = $station->getCodeExterne();
            if (null !== $code) {
                $stationsParCode[$code] = $station;
            }
        }

        $lignesDejaVues = [];
        $nbTronconsTotal = 0;
        $nbIgnores = 0;

        $fichier = fopen(self::TRONCONS_CSV, 'r');
        fgetcsv($fichier); // en-tete
        while (false !== ($ligneCsv = fgetcsv($fichier))) {
            [$routeLabel, $zdcA, $zdcB, $nomA, $nomB, $dureeMediane] = $ligneCsv;

            if (!isset($lignesDejaVues[$routeLabel])) {
                $lignesDejaVues[$routeLabel] = $this->preparerLigne($io, $routeLabel);
            }
            $ligne = $lignesDejaVues[$routeLabel];
            if (null === $ligne) {
                continue;
            }

            $desserteA = $this->trouverDesserte($ligne, $stationsParCode, $zdcA, $nomA);
            $desserteB = $this->trouverDesserte($ligne, $stationsParCode, $zdcB, $nomB);
            if (null === $desserteA || null === $desserteB) {
                $io->warning(sprintf(
                    'Ligne %s : desserte introuvable pour "%s" ou "%s" (station sans codeExterne correspondant ?), tronçon ignoré.',
                    $routeLabel,
                    $nomA,
                    $nomB,
                ));
                ++$nbIgnores;
                continue;
            }

            $duree = '' !== $dureeMediane ? (int) $dureeMediane : null;
            $this->creerTronconBidirectionnel($desserteA, $desserteB, $duree);
            ++$nbTronconsTotal;
        }
        fclose($fichier);

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d troncons crees sur %d ligne(s) de bus (%d ignores, desserte introuvable).',
            $nbTronconsTotal,
            \count(array_filter($lignesDejaVues)),
            $nbIgnores,
        ));

        return Command::SUCCESS;
    }

    /**
     * Retrouve la Ligne pour ce label et verifie qu'elle n'a pas deja de troncons (usage unique).
     * Retourne null si la ligne doit etre ignoree (introuvable, code inconnu, ou deja construite).
     */
    private function preparerLigne(SymfonyStyle $io, string $routeLabel): ?Ligne
    {
        $code = self::LIGNES_CODES[$routeLabel] ?? null;
        if (null === $code) {
            $io->warning("Ligne $routeLabel : aucun codeExterne connu, ignoree.");

            return null;
        }

        $ligne = $this->ligneRepository->findOneBy(['codeExterne' => $code]);
        if (null === $ligne) {
            $io->warning("Ligne $routeLabel (codeExterne $code) introuvable en base, ignoree.");

            return null;
        }

        $dejaConstruite = \count($ligne->getDessertes()->filter(
            fn (Desserte $d) => $d->getTronconDessertes()->count() > 0
        )) > 0;
        if ($dejaConstruite) {
            $io->warning("Ligne $routeLabel deja construite, ignoree.");

            return null;
        }

        return $ligne;
    }

    /**
     * @param array<string, Station> $stationsParCode
     */
    private function trouverDesserte(Ligne $ligne, array $stationsParCode, string $zdc, string $nomPourErreur): ?Desserte
    {
        $station = $stationsParCode[$zdc] ?? null;
        if (null === $station) {
            return null;
        }

        return $this->desserteRepository->findOneBy(['ligne' => $ligne, 'station' => $station]);
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
    }
}
