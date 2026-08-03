<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
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
    }

    private function createStation(string $label): Station
    {
        $station = new Station();
        $station->setLabel($label);
        $this->manager->persist($station);

        return $station;
    }

    private function createLigne(): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
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

        $crawler = $this->client->request('GET', sprintf('/trajet?origine=%d&destination=%d', $a->getId(), $b->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('#trajet-graphe');
        self::assertSelectorTextContains('body', '2 min');
    }

    public function testSansCheminPossibleAfficheUnMessageDErreur(): void
    {
        $ligne1 = $this->createLigne();
        $ligne2 = new Ligne();
        $ligne2->setLabel('2');
        $this->manager->persist($ligne2);

        $a = $this->createDesserte($ligne1, $this->createStation('A'));
        $b = $this->createDesserte($ligne2, $this->createStation('B'));
        $this->manager->flush();
        $this->manager->clear();

        $this->client->request('GET', sprintf('/trajet?origine=%d&destination=%d', $a->getId(), $b->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.alert', 'Aucun trajet trouvé');
    }
}
