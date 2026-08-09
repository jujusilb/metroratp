<?php

namespace App\Tests\Controller;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use App\Entity\TypeTransport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class TrajetControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    private function createStation(string $label, ?float $schemaX = null, ?float $schemaY = null): Station
    {
        $station = new Station();
        $station->setLabel($label);
        $station->setSchemaX($schemaX);
        $station->setSchemaY($schemaY);
        $this->manager->persist($station);

        return $station;
    }

    private ?TypeTransport $typeTransportMetro = null;

    /**
     * Le calculateur de trajet filtre par Ligne::getModeFiltre() (voir la case a cocher
     * "Metro/Tram/RER/Bus" du formulaire) : sans typeTransport, une ligne de test serait
     * exclue du graphe par defaut.
     */
    private function typeTransportMetro(): TypeTransport
    {
        if (null === $this->typeTransportMetro) {
            $this->typeTransportMetro = new TypeTransport();
            $this->typeTransportMetro->setLabel('Métro');
            $this->manager->persist($this->typeTransportMetro);
        }

        return $this->typeTransportMetro;
    }

    private function createLigne(): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $ligne->setTypeTransport($this->typeTransportMetro());
        $this->manager->persist($ligne);

        return $ligne;
    }

    private ?TypeTransport $typeTransportBus = null;

    private function typeTransportBus(): TypeTransport
    {
        if (null === $this->typeTransportBus) {
            $this->typeTransportBus = new TypeTransport();
            $this->typeTransportBus->setLabel('Bus');
            $this->manager->persist($this->typeTransportBus);
        }

        return $this->typeTransportBus;
    }

    private function createLigneBus(string $label): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel($label);
        $ligne->setTypeTransport($this->typeTransportBus());
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

    private function linkTroncon(Desserte $a, Desserte $b): void
    {
        $departType = new TypeDesserte();
        $departType->setLabel('Départ');
        $this->manager->persist($departType);

        $arriveeType = new TypeDesserte();
        $arriveeType->setLabel('Arrivée');
        $this->manager->persist($arriveeType);

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

    public function testIndexSansParametresAfficheLeFormulaire(): void
    {
        $this->client->request('GET', '/trajet');

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('trajet');
    }

    public function testAvecOrigineEtDestinationAfficheLeResultat(): void
    {
        $ligne = $this->createLigne();
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $this->linkTroncon($a, $b);
        $this->manager->flush();
        $this->manager->clear();

        $crawler = $this->client->request('GET', sprintf('/trajet?origine=%d&destination=%d', $a->getStation()->getId(), $b->getStation()->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('#trajet-carte');
        self::assertSelectorTextContains('body', '2 min');
    }

    public function testAvecCoordonneesSchematiquesAlimenteLaCarte(): void
    {
        $ligne = $this->createLigne();
        $a = $this->createDesserte($ligne, $this->createStation('A', 10.0, 20.0));
        $b = $this->createDesserte($ligne, $this->createStation('B', 15.0, 25.0));
        $this->linkTroncon($a, $b);
        $this->manager->flush();
        $this->manager->clear();

        $crawler = $this->client->request('GET', sprintf('/trajet?origine=%d&destination=%d', $a->getStation()->getId(), $b->getStation()->getId()));

        self::assertResponseStatusCodeSame(200);
        $carteJson = $crawler->filter('#trajet-carte')->attr('data-carte');
        self::assertNotNull($carteJson);
        $carte = json_decode($carteJson, true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $carte['reseau']);
        self::assertEqualsWithDelta(10.0, $carte['reseau'][0]['x1'], 0.001);
        self::assertSame('A', $carte['trajet'][0]['labelDepart']);
    }

    public function testAvecCorrespondanceAfficheLaVueSimpleEtDetaillee(): void
    {
        $ligne1 = $this->createLigne();
        $ligne2 = new Ligne();
        $ligne2->setLabel('2');
        $ligne2->setTypeTransport($this->typeTransportMetro());
        $this->manager->persist($ligne2);

        $a = $this->createDesserte($ligne1, $this->createStation('A'));
        $pivot1 = $this->createDesserte($ligne1, $this->createStation('Pivot'));
        $pivot2 = $this->createDesserte($ligne2, $pivot1->getStation());
        $b = $this->createDesserte($ligne2, $this->createStation('B'));
        $this->linkTroncon($a, $pivot1);
        $this->linkTroncon($pivot2, $b);

        $correspondance = new Correspondance();
        $correspondance->setDesserteA($pivot1);
        $correspondance->setDesserteB($pivot2);
        $correspondance->setInZone(true);
        $this->manager->persist($correspondance);

        $this->manager->flush();
        $this->manager->clear();

        $this->client->request('GET', sprintf('/trajet?origine=%d&destination=%d', $a->getStation()->getId(), $b->getStation()->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('#vue-simple');
        self::assertSelectorExists('#vue-detaillee');
        self::assertSelectorTextContains('#vue-simple', 'A');
        self::assertSelectorTextContains('#vue-simple', 'Pivot');
        self::assertSelectorTextContains('#vue-simple', 'B');
        self::assertSelectorTextContains('#vue-detaillee', 'Correspondance');
    }

    public function testSansCheminPossibleAfficheUnMessageDErreur(): void
    {
        $ligne1 = $this->createLigne();
        $ligne2 = new Ligne();
        $ligne2->setLabel('2');
        $ligne2->setTypeTransport($this->typeTransportMetro());
        $this->manager->persist($ligne2);

        $a = $this->createDesserte($ligne1, $this->createStation('A'));
        $b = $this->createDesserte($ligne2, $this->createStation('B'));
        $this->manager->flush();
        $this->manager->clear();

        $this->client->request('GET', sprintf('/trajet?origine=%d&destination=%d', $a->getStation()->getId(), $b->getStation()->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.alert', 'Aucun trajet trouvé');
    }

    public function testSansStationSelectionneeNaffichePasDErreurEtNePlantePas(): void
    {
        $this->client->request('GET', '/trajet?origine=&destination=');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorNotExists('.alert');
    }

    public function testRechercheStationDedupliqueParStationPasParDesserte(): void
    {
        $ligne1 = $this->createLigne();
        $ligne2 = new Ligne();
        $ligne2->setLabel('4');
        $ligne2->setTypeTransport($this->typeTransportMetro());
        $this->manager->persist($ligne2);

        $chatelet = $this->createStation('Châtelet');
        $this->createDesserte($ligne1, $chatelet);
        $this->createDesserte($ligne2, $chatelet);
        // Une station reelle a toujours au moins une desserte : sans ca, rechercheStation()
        // l'exclurait desormais (aucun mode ne la dessert, voir
        // testRechercheStationExclutLesStationsDesserviesUniquementParUnModeDecoche).
        $this->createDesserte($ligne1, $this->createStation('Château Landon'));
        $this->createDesserte($ligne1, $this->createStation('Nation'));
        $this->manager->flush();
        $this->manager->clear();

        $this->client->request('GET', '/trajet/recherche-station?q=chat');

        self::assertResponseStatusCodeSame(200);
        $resultats = json_decode($this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(2, $resultats);
        $labels = array_column($resultats, 'label');
        self::assertContains('Châtelet', $labels);
        self::assertContains('Château Landon', $labels);
    }

    public function testRechercheStationExclutLesStationsDesserviesUniquementParUnModeDecoche(): void
    {
        $ligneMetro = $this->createLigne();
        $ligneBus = $this->createLigneBus('131');

        $mixte = $this->createStation('Châtelet Mixte');
        $this->createDesserte($ligneMetro, $mixte);
        $this->createDesserte($ligneBus, $mixte);

        $busSeul = $this->createStation('Châtelet Bus Seul');
        $this->createDesserte($ligneBus, $busSeul);

        $this->manager->flush();
        $this->manager->clear();

        // Seul le metro est coche : la station 100% bus ne doit plus apparaitre du tout, et la
        // station mixte ne doit plus proposer que le mode metro (pas de sous-option bus).
        $this->client->request('GET', '/trajet/recherche-station?q=Châtelet&modes[]=metro');

        self::assertResponseStatusCodeSame(200);
        $resultats = json_decode($this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $labels = array_column($resultats, 'label');
        self::assertContains('Châtelet Mixte', $labels);
        self::assertNotContains('Châtelet Bus Seul', $labels);

        $mixteResultat = $resultats[array_search('Châtelet Mixte', $labels, true)];
        self::assertSame(['metro'], $mixteResultat['modes']);
    }

    public function testRechercheStationVideRetourneUneListeVide(): void
    {
        $this->client->request('GET', '/trajet/recherche-station?q=');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('[]', $this->client->getResponse()->getContent());
    }
}
