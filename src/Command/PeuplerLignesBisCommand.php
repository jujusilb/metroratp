<?php

namespace App\Command;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use App\Entity\Direction;
use App\Entity\Ligne;
use App\Entity\MaterielLigne;
use App\Entity\Mission;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use App\Entity\TypeTroncon;
use App\Repository\LigneRepository;
use App\Repository\MaterielRepository;
use App\Repository\ServiceRepository;
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
 * Peuple les lignes 3bis et 7bis (stations, dessertes, troncons, directions, missions,
 * materiel roulant, correspondances), qui n'existaient jusqu'ici que comme lignes vides.
 *
 * Topologie reelle (verifiee via le GTFS IDFM, routes IDFM:C01386/C01387) :
 * - 3bis : simple et lineaire, Porte des Lilas <-> Saint-Fargeau <-> Pelleport <-> Gambetta.
 * - 7bis : un tronc commun Louis Blanc <-> Jauresa <-> Bolivar <-> Buttes Chaumont <-> Botzaris,
 *   puis un embranchement ASYMETRIQUE a sens unique : direction Pre-Saint-Gervais, on passe
 *   par Place des Fetes ; direction Louis Blanc (retour), on passe par Danube. Ce ne sont donc
 *   pas 2 sens d'un meme troncon mais 4 troncons a sens unique distincts.
 *
 * Commande a usage unique : refuse de s'executer si les lignes ont deja des dessertes.
 */
#[AsCommand(name: 'app:peupler-lignes-bis', description: 'Peuple integralement les lignes 3bis et 7bis (stations, troncons, missions, materiel, correspondances)')]
class PeuplerLignesBisCommand extends Command
{
    private TypeDesserte $depart;
    private TypeDesserte $arrivee;
    private TypeTroncon $interieur;

    /** @var array<string, Station> */
    private array $stationsCache = [];

    /** @var array<int, Direction> cle = ligneId.'|'.desserteTerminusId */
    private array $directionsCache = [];

    /**
     * TronconDesserte de role "Depart" indexes par troncon+desserte : le cote inverse
     * (Troncon::tronconDessertes, mappedBy) n'est pas synchronise automatiquement par
     * Doctrine quand on ne fait que setTroncon() cote proprietaire, donc on ne peut pas
     * relire $troncon->getTronconDessertes() juste apres les avoir crees.
     *
     * @var array<string, TronconDesserte>
     */
    private array $departTdCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LigneRepository $ligneRepository,
        private readonly StationRepository $stationRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly TypeDesserteRepository $typeDesserteRepository,
        private readonly TypeTronconRepository $typeTronconRepository,
        private readonly MaterielRepository $materielRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ligne3bis = $this->ligneRepository->findOneBy(['label' => '3b']);
        $ligne7bis = $this->ligneRepository->findOneBy(['label' => '7b']);
        if (null === $ligne3bis || null === $ligne7bis) {
            $io->error('Ligne 3b et/ou 7b introuvable en base.');

            return Command::FAILURE;
        }

        if (\count($ligne3bis->getDessertes()) > 0 || \count($ligne7bis->getDessertes()) > 0) {
            $io->error('La ligne 3b ou 7b a deja des dessertes : la commande a probablement deja ete executee.');

            return Command::FAILURE;
        }

        $this->depart = $this->typeDesserteRepository->findOneBy(['label' => 'Départ']);
        $this->arrivee = $this->typeDesserteRepository->findOneBy(['label' => 'Arrivée']);
        $this->interieur = $this->typeTronconRepository->findOneBy(['label' => 'Interieur']);
        $serviceUnique = $this->serviceRepository->findOneBy(['label' => 'Unique']);
        $spragueThomson = $this->materielRepository->findOneBy(['label' => 'Sprague-Thomson']);
        $mf67 = $this->materielRepository->findOneBy(['label' => 'MF 67']);

        // ---- Ligne 3bis : simple, lineaire, bidirectionnelle ----
        $dessertes3bis = $this->creerDessertesEnSequence($ligne3bis, ['Porte des Lilas', 'Saint-Fargeau', 'Pelleport', 'Gambetta']);
        $dirVersGambetta = $this->obtenirDirection($ligne3bis, $dessertes3bis[3]);
        $dirVersPorteDesLilas = $this->obtenirDirection($ligne3bis, $dessertes3bis[0]);

        for ($i = 0; $i < 3; ++$i) {
            $numero = $i + 1;
            $troncon = $this->creerTronconBidirectionnel($dessertes3bis[$i], $dessertes3bis[$i + 1]);
            $this->creerMission($troncon, $dessertes3bis[$i + 1], $dirVersPorteDesLilas, $serviceUnique, $numero);
            $this->creerMission($troncon, $dessertes3bis[$i], $dirVersGambetta, $serviceUnique, $numero);
        }

        $this->ajouterMateriel($ligne3bis, $spragueThomson, '1921-01-01', '1971-12-31');
        $this->ajouterMateriel($ligne3bis, $mf67, '1971-01-01', null);

        // ---- Ligne 7bis : tronc commun + embranchement asymetrique a sens unique ----
        $troncCommun = $this->creerDessertesEnSequence($ligne7bis, ['Louis Blanc', 'Jaurès', 'Bolivar', 'Buttes Chaumont', 'Botzaris']);
        [$louisBlanc, $jaures, $bolivar, $buttesChaumont, $botzaris] = $troncCommun;

        $placeDesFetes = $this->obtenirOuCreerDesserte($ligne7bis, 'Place des Fêtes');
        $preSaintGervais = $this->obtenirOuCreerDesserte($ligne7bis, 'Pré-Saint-Gervais');
        $danube = $this->obtenirOuCreerDesserte($ligne7bis, 'Danube');

        $dirVersPreSaintGervais = $this->obtenirDirection($ligne7bis, $preSaintGervais);
        $dirVersLouisBlanc = $this->obtenirDirection($ligne7bis, $louisBlanc);

        // Troncons partages (numero 1 a 4), un sens par direction sur le meme troncon.
        $sequenceCommune = [$louisBlanc, $jaures, $bolivar, $buttesChaumont, $botzaris];
        for ($i = 0; $i < 4; ++$i) {
            $numero = $i + 1;
            $troncon = $this->creerTronconBidirectionnel($sequenceCommune[$i], $sequenceCommune[$i + 1]);
            $this->creerMission($troncon, $sequenceCommune[$i + 1], $dirVersLouisBlanc, $serviceUnique, $numero);
            $this->creerMission($troncon, $sequenceCommune[$i], $dirVersPreSaintGervais, $serviceUnique, $numero);
        }

        // Branche a sens unique vers Pre-Saint-Gervais (via Place des Fetes), numero 5-6.
        $tronconBotzarisPlaceDesFetes = $this->creerTronconSensUnique($botzaris, $placeDesFetes);
        $this->creerMission($tronconBotzarisPlaceDesFetes, $botzaris, $dirVersPreSaintGervais, $serviceUnique, 5);
        $tronconPlaceDesFetesPreSaintGervais = $this->creerTronconSensUnique($placeDesFetes, $preSaintGervais);
        $this->creerMission($tronconPlaceDesFetesPreSaintGervais, $placeDesFetes, $dirVersPreSaintGervais, $serviceUnique, 6);

        // Branche retour a sens unique vers Louis Blanc (via Danube), numero 5-6.
        $tronconPreSaintGervaisDanube = $this->creerTronconSensUnique($preSaintGervais, $danube);
        $this->creerMission($tronconPreSaintGervaisDanube, $preSaintGervais, $dirVersLouisBlanc, $serviceUnique, 6);
        $tronconDanubeBotzaris = $this->creerTronconSensUnique($danube, $botzaris);
        $this->creerMission($tronconDanubeBotzaris, $danube, $dirVersLouisBlanc, $serviceUnique, 5);

        $this->ajouterMateriel($ligne7bis, $spragueThomson, '1911-01-01', '1967-12-31');
        $this->ajouterMateriel($ligne7bis, $mf67, '1967-01-01', null);

        // Flush intermediaire : les correspondances comparent les id (desserte_a_id < desserte_b_id)
        // dans un lifecycle callback PrePersist, il faut donc que les nouvelles dessertes aient
        // deja un id reel avant d'y toucher.
        $this->entityManager->flush();

        // ---- Correspondances avec les autres lignes ----
        $this->ajouterCorrespondance($dessertes3bis[0], 'Porte des Lilas', '11');
        $this->ajouterCorrespondance($dessertes3bis[3], 'Gambetta', '3');
        $this->ajouterCorrespondance($louisBlanc, 'Louis Blanc', '5');
        $this->ajouterCorrespondance($louisBlanc, 'Louis Blanc', '7');
        $this->ajouterCorrespondance($jaures, 'Jaurès', '2');
        $this->ajouterCorrespondance($jaures, 'Jaurès', '5');
        $this->ajouterCorrespondance($placeDesFetes, 'Place des Fêtes', '11');

        $this->entityManager->flush();

        $io->success('Lignes 3bis et 7bis peuplees : stations, dessertes, troncons, directions, missions, materiel roulant et correspondances.');

        return Command::SUCCESS;
    }

    private function obtenirOuCreerStation(string $label): Station
    {
        if (isset($this->stationsCache[$label])) {
            return $this->stationsCache[$label];
        }

        $station = $this->stationRepository->findOneBy(['label' => $label]);
        if (null === $station) {
            $station = new Station();
            $station->setLabel($label);
            $this->entityManager->persist($station);
        }

        return $this->stationsCache[$label] = $station;
    }

    private function obtenirOuCreerDesserte(Ligne $ligne, string $stationLabel): Desserte
    {
        $station = $this->obtenirOuCreerStation($stationLabel);

        $desserte = new Desserte();
        $desserte->setLigne($ligne);
        $desserte->setStation($station);
        $this->entityManager->persist($desserte);

        return $desserte;
    }

    /**
     * @param string[] $stationsLabels
     *
     * @return Desserte[]
     */
    private function creerDessertesEnSequence(Ligne $ligne, array $stationsLabels): array
    {
        return array_map(fn (string $label): Desserte => $this->obtenirOuCreerDesserte($ligne, $label), $stationsLabels);
    }

    private function creerTronconBidirectionnel(Desserte $a, Desserte $b): Troncon
    {
        $troncon = new Troncon();
        $troncon->setTypeTroncon($this->interieur);
        $this->entityManager->persist($troncon);

        $this->creerTronconDesserte($troncon, $a, $this->depart);
        $this->creerTronconDesserte($troncon, $b, $this->arrivee);
        $this->creerTronconDesserte($troncon, $b, $this->depart);
        $this->creerTronconDesserte($troncon, $a, $this->arrivee);

        return $troncon;
    }

    private function creerTronconSensUnique(Desserte $depart, Desserte $arrivee): Troncon
    {
        $troncon = new Troncon();
        $troncon->setTypeTroncon($this->interieur);
        $this->entityManager->persist($troncon);

        $this->creerTronconDesserte($troncon, $depart, $this->depart);
        $this->creerTronconDesserte($troncon, $arrivee, $this->arrivee);

        return $troncon;
    }

    private function creerTronconDesserte(Troncon $troncon, Desserte $desserte, TypeDesserte $role): TronconDesserte
    {
        $tronconDesserte = new TronconDesserte();
        $tronconDesserte->setTroncon($troncon);
        $tronconDesserte->setDesserte($desserte);
        $tronconDesserte->setTypeDesserte($role);
        $this->entityManager->persist($tronconDesserte);

        if ($role === $this->depart) {
            $this->departTdCache[spl_object_id($troncon).'|'.spl_object_id($desserte)] = $tronconDesserte;
        }

        return $tronconDesserte;
    }

    private function obtenirDirection(Ligne $ligne, Desserte $desserteTerminus): Direction
    {
        $cle = $ligne->getId().'|'.spl_object_id($desserteTerminus);
        if (isset($this->directionsCache[$cle])) {
            return $this->directionsCache[$cle];
        }

        $direction = new Direction();
        $direction->setLigne($ligne);
        $direction->setDesserteTerminus($desserteTerminus);
        $this->entityManager->persist($direction);

        return $this->directionsCache[$cle] = $direction;
    }

    /**
     * Cree la mission dont le depart est $desserteDepart sur $troncon, pour la direction donnee.
     */
    private function creerMission(Troncon $troncon, Desserte $desserteDepart, Direction $direction, \App\Entity\Service $service, int $numero): Mission
    {
        $tronconDesserteDepart = $this->departTdCache[spl_object_id($troncon).'|'.spl_object_id($desserteDepart)] ?? null;

        $mission = new Mission();
        $mission->setTronconDesserte($tronconDesserteDepart);
        $mission->setDirection($direction);
        $mission->setService($service);
        $mission->setNumero($numero);
        $this->entityManager->persist($mission);

        return $mission;
    }

    private function ajouterMateriel(Ligne $ligne, \App\Entity\Materiel $materiel, string $arrivee, ?string $fin): void
    {
        $materielLigne = new MaterielLigne();
        $materielLigne->setLigne($ligne);
        $materielLigne->setMateriel($materiel);
        $materielLigne->setArrivee(new \DateTime($arrivee));
        $materielLigne->setFin(null !== $fin ? new \DateTime($fin) : null);
        $this->entityManager->persist($materielLigne);
    }

    private function ajouterCorrespondance(Desserte $desserteBis, string $stationLabel, string $autreLigneLabel): void
    {
        $autreLigne = $this->ligneRepository->findOneBy(['label' => $autreLigneLabel]);
        $station = $this->stationRepository->findOneBy(['label' => $stationLabel]);
        if (null === $autreLigne || null === $station) {
            return;
        }

        $autreDesserte = null;
        foreach ($station->getDessertes() as $candidate) {
            if ($candidate->getLigne() === $autreLigne) {
                $autreDesserte = $candidate;
                break;
            }
        }
        if (null === $autreDesserte) {
            return;
        }

        $correspondance = new Correspondance();
        $correspondance->setDesserteA($desserteBis);
        $correspondance->setDesserteB($autreDesserte);
        $correspondance->setInZone(true);
        $this->entityManager->persist($correspondance);
    }
}
