<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Direction;
use App\Entity\Ligne;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class DirectionControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Direction> $directionRepository */
    private EntityRepository $directionRepository;
    private string $path = '/direction/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->directionRepository = $this->manager->getRepository(Direction::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Directions');
    }

    public function testNewSansLigneRedirigeVersLaListeDesLignes(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseRedirects('/ligne');
    }

    public function testNewNeProposeQueLesDessertesDeLaLigne(): void
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);

        $autreLigne = new Ligne();
        $autreLigne->setLabel('2');
        $this->manager->persist($autreLigne);

        $stationA = new Station();
        $stationA->setLabel('Terminus ligne 1');
        $this->manager->persist($stationA);
        $desserteLigne1 = new Desserte();
        $desserteLigne1->setLigne($ligne);
        $desserteLigne1->setStation($stationA);
        $this->manager->persist($desserteLigne1);

        $stationB = new Station();
        $stationB->setLabel('Terminus ligne 2');
        $this->manager->persist($stationB);
        $desserteLigne2 = new Desserte();
        $desserteLigne2->setLigne($autreLigne);
        $desserteLigne2->setStation($stationB);
        $this->manager->persist($desserteLigne2);

        $this->manager->flush();

        $crawler = $this->client->request('GET', sprintf('%snew?ligne=%s', $this->path, $ligne->getId()));

        self::assertResponseStatusCodeSame(200);
        $options = $crawler->filter('select[name="direction[desserteTerminus]"] option')->each(fn ($node) => $node->text());
        self::assertContains('Terminus ligne 1', $options);
        self::assertNotContains('Terminus ligne 2', $options, 'Une desserte d\'une autre ligne ne doit pas apparaitre.');

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['direction[desserteTerminus]'] = (string) $desserteLigne1->getId();
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/ligne/%s', $ligne->getId()));
        self::assertSame(1, $this->directionRepository->count([]));
    }

    public function testShow(): void
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);
        $station = new Station();
        $station->setLabel('Terminus');
        $this->manager->persist($station);
        $desserte = new Desserte();
        $desserte->setLigne($ligne);
        $desserte->setStation($station);
        $this->manager->persist($desserte);

        $fixture = new Direction();
        $fixture->setLigne($ligne);
        $fixture->setDesserteTerminus($desserte);
        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Direction');
    }

    public function testRemove(): void
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);
        $station = new Station();
        $station->setLabel('Terminus');
        $this->manager->persist($station);
        $desserte = new Desserte();
        $desserte->setLigne($ligne);
        $desserte->setStation($station);
        $this->manager->persist($desserte);

        $fixture = new Direction();
        $fixture->setLigne($ligne);
        $fixture->setDesserteTerminus($desserte);
        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects(sprintf('/ligne/%s', $ligne->getId()));
        self::assertSame(0, $this->directionRepository->count([]));
    }
}
