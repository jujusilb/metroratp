<?php

namespace App\Tests\Service;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use App\Entity\HoraireLigne;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use App\Service\Trajet\Etape;
use App\Service\TrajetFinder;
use App\Tests\Controller\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class TrajetFinderTest extends DatabaseTestCase
{
    private EntityManagerInterface $manager;
    private TrajetFinder $trajetFinder;

    protected function setUp(): void
    {
        static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->trajetFinder = static::getContainer()->get(TrajetFinder::class);
        $this->resetDatabase($this->manager);
    }

    private function createStation(string $label): Station
    {
        $station = new Station();
        $station->setLabel($label);
        $this->manager->persist($station);

        return $station;
    }

    private function createLigne(string $label): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel($label);
        $this->manager->persist($ligne);

        return $ligne;
    }

    private function createDesserte(Ligne $ligne, Station $station): Desserte
    {
        $desserte = new Desserte();
        $desserte->setLigne($ligne);
        $desserte->setStation($station);
        $this->manager->persist($desserte);

        return $desserte;
    }

    private function createTypeDesserte(string $label): TypeDesserte
    {
        $typeDesserte = new TypeDesserte();
        $typeDesserte->setLabel($label);
        $this->manager->persist($typeDesserte);

        return $typeDesserte;
    }

    /**
     * Relie deux dessertes par un troncon bidirectionnel (comme le fait le vrai schema :
     * 2 lignes troncon_desserte, une "Depart" pour chaque sens).
     */
    private function linkTroncon(Desserte $a, Desserte $b): void
    {
        $departType = $this->createTypeDesserte('Départ');
        $arriveeType = $this->createTypeDesserte('Arrivée');

        $troncon = new Troncon();
        $this->manager->persist($troncon);

        $tdA = new TronconDesserte();
        $tdA->setTroncon($troncon);
        $tdA->setDesserte($a);
        $tdA->setTypeDesserte($departType);
        $this->manager->persist($tdA);

        $tdB = new TronconDesserte();
        $tdB->setTroncon($troncon);
        $tdB->setDesserte($b);
        $tdB->setTypeDesserte($arriveeType);
        $this->manager->persist($tdB);
    }

    public function testTrouveLeCheminLePlusCourtSurUneLigneSimple(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $c = $this->createDesserte($ligne, $this->createStation('C'));

        $this->linkTroncon($a, $b);
        $this->linkTroncon($b, $c);

        $this->manager->flush();
        $this->manager->clear();

        $resultat = $this->trajetFinder->trouverPlusCourtChemin($a->getStation()->getId(), $c->getStation()->getId());

        self::assertNotNull($resultat);
        self::assertCount(2, $resultat->etapes);
        self::assertSame(Etape::TYPE_TRONCON, $resultat->etapes[0]->type);
        self::assertSame(Etape::TYPE_TRONCON, $resultat->etapes[1]->type);
        self::assertSame(4.0, $resultat->dureeMinutesTotale);
        self::assertSame(0, $resultat->getNombreCorrespondances());
    }

    public function testOrigineEtDestinationIdentiquesRenvoieUnTrajetVide(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $this->manager->flush();
        $this->manager->clear();

        $resultat = $this->trajetFinder->trouverPlusCourtChemin($a->getStation()->getId(), $a->getStation()->getId());

        self::assertNotNull($resultat);
        self::assertCount(0, $resultat->etapes);
        self::assertSame(0.0, $resultat->dureeMinutesTotale);
    }

    public function testAucunCheminEntreDeuxDessertesNonConnectees(): void
    {
        $ligne1 = $this->createLigne('1');
        $ligne2 = $this->createLigne('2');
        $a = $this->createDesserte($ligne1, $this->createStation('A'));
        $b = $this->createDesserte($ligne2, $this->createStation('B'));
        $this->manager->flush();
        $this->manager->clear();

        $resultat = $this->trajetFinder->trouverPlusCourtChemin($a->getStation()->getId(), $b->getStation()->getId());

        self::assertNull($resultat);
    }

    public function testUtiliseUneCorrespondancePourChangerDeLigne(): void
    {
        // Deux petites lignes independantes, reliees uniquement par une correspondance a la
        // station B (commune aux deux lignes).
        $ligne1 = $this->createLigne('1');
        $ligne2 = $this->createLigne('2');
        $stationB = $this->createStation('B');

        $a = $this->createDesserte($ligne1, $this->createStation('A'));
        $b1 = $this->createDesserte($ligne1, $stationB);
        $b2 = $this->createDesserte($ligne2, $stationB);
        $c = $this->createDesserte($ligne2, $this->createStation('C'));

        $this->linkTroncon($a, $b1);
        $this->linkTroncon($b2, $c);

        $correspondance = new Correspondance();
        $correspondance->setDesserteA($b1);
        $correspondance->setDesserteB($b2);
        $correspondance->setDistance(90); // 90m / 0.9m/s / 60 = 1.67 min, arrondi a 1.7, + 2 min d'attente = 3.7
        $this->manager->persist($correspondance);

        $this->manager->flush();
        $this->manager->clear();

        $resultat = $this->trajetFinder->trouverPlusCourtChemin($a->getStation()->getId(), $c->getStation()->getId());

        self::assertNotNull($resultat);
        self::assertCount(3, $resultat->etapes);
        self::assertSame(1, $resultat->getNombreCorrespondances());

        $etapeCorrespondance = $resultat->etapes[1];
        self::assertSame(Etape::TYPE_CORRESPONDANCE, $etapeCorrespondance->type);
        self::assertSame(3.7, $etapeCorrespondance->dureeMinutes);
    }

    private function createHoraireLigne(Ligne $ligne, string $typeJour, string $premierDepart, string $dernierDepart): void
    {
        $horaire = new HoraireLigne();
        $horaire->setLigne($ligne);
        $horaire->setTypeJour($typeJour);
        $horaire->setPremierDepart(new \DateTime($premierDepart));
        $horaire->setDernierDepart(new \DateTime($dernierDepart));
        $this->manager->persist($horaire);
    }

    public function testLigneFermeeAuMomentDemandeEstExclue(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $this->linkTroncon($a, $b);
        // Seule plage connue pour "Semaine" : 22h-23h, tres loin du moment demande (14h).
        $this->createHoraireLigne($ligne, 'Semaine', '22:00', '23:00');

        $this->manager->flush();
        $this->manager->clear();

        $mercrediMidi = new \DateTimeImmutable('next wednesday 14:00');
        $resultat = $this->trajetFinder->trouverPlusCourtChemin($a->getStation()->getId(), $b->getStation()->getId(), moment: $mercrediMidi);

        self::assertNull($resultat, 'La ligne est fermee a 14h (plage 22h-23h uniquement) : aucun trajet ne doit etre trouve.');
    }

    public function testLigneOuverteAuMomentDemandeEstUtilisee(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $this->linkTroncon($a, $b);
        $this->createHoraireLigne($ligne, 'Semaine', '05:00', '23:59');

        $this->manager->flush();
        $this->manager->clear();

        $mercrediMidi = new \DateTimeImmutable('next wednesday 14:00');
        $resultat = $this->trajetFinder->trouverPlusCourtChemin($a->getStation()->getId(), $b->getStation()->getId(), moment: $mercrediMidi);

        self::assertNotNull($resultat);
        self::assertCount(1, $resultat->etapes);
    }

    public function testPlageHoraireQuiFranchitMinuitEstBienCirculaire(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $this->linkTroncon($a, $b);
        // Plage 22h -> 02h (franchit minuit) : 23h30 doit etre considere "en service".
        $this->createHoraireLigne($ligne, 'Semaine', '22:00', '02:00');

        $this->manager->flush();
        $this->manager->clear();

        $mercrediSoir = new \DateTimeImmutable('next wednesday 23:30');
        $resultat = $this->trajetFinder->trouverPlusCourtChemin($a->getStation()->getId(), $b->getStation()->getId(), moment: $mercrediSoir);

        self::assertNotNull($resultat, "23h30 tombe dans la plage 22h->02h (franchit minuit) : un trajet doit etre trouve.");
    }

    public function testSansMomentAucunFiltreHoraireNestApplique(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $this->linkTroncon($a, $b);
        $this->createHoraireLigne($ligne, 'Semaine', '22:00', '23:00');

        $this->manager->flush();
        $this->manager->clear();

        $resultat = $this->trajetFinder->trouverPlusCourtChemin($a->getStation()->getId(), $b->getStation()->getId());

        self::assertNotNull($resultat, 'Sans $moment fourni (comportement historique), aucune ligne ne doit etre exclue par horaire.');
    }
}
