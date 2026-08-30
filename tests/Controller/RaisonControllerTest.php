<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Raison;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class RaisonControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Raison> $raisonRepository */
    private EntityRepository $raisonRepository;
    private string $path = '/raison/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->raisonRepository = $this->manager->getRepository(Raison::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains("Raisons d'inactivité");
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'raison[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/raison');

        self::assertSame(1, $this->raisonRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Raison();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Raison');
    }

    public function testEdit(): void
    {
        $fixture = new Raison();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'raison[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/raison');

        $fixture = $this->raisonRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testEditAssigneDesStationsEtMarqueInactive(): void
    {
        $stationA = new Station();
        $stationA->setLabel('Fantôme A');
        $this->manager->persist($stationA);

        $stationB = new Station();
        $stationB->setLabel('Fantôme B');
        $this->manager->persist($stationB);

        $fixture = new Raison();
        $fixture->setLabel('Fermée pour la guerre');
        $this->manager->persist($fixture);
        $this->manager->flush();

        self::assertTrue($stationA->estActive());

        $crawler = $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $form = $crawler->selectButton('Mettre à jour')->form();
        $form['raison[stations]']->setValue([(string) $stationA->getId(), (string) $stationB->getId()]);
        $this->client->submit($form);

        self::assertResponseRedirects('/raison');

        $this->manager->clear();
        $updatedStationA = $this->manager->getRepository(Station::class)->find($stationA->getId());

        self::assertCount(1, $updatedStationA->getRaisons());
        self::assertFalse($updatedStationA->estActive());
    }

    public function testEditAssigneDesDessertesEtMarqueInactive(): void
    {
        $ligne = new Ligne();
        $ligne->setLabel('5');
        $this->manager->persist($ligne);

        $station = new Station();
        $station->setLabel('Arsenal');
        $this->manager->persist($station);

        $desserte = new Desserte();
        $desserte->setStation($station);
        $desserte->setLigne($ligne);
        $this->manager->persist($desserte);

        $fixture = new Raison();
        $fixture->setLabel('Fermée pour la guerre');
        $this->manager->persist($fixture);
        $this->manager->flush();

        self::assertTrue($desserte->estActive());
        self::assertTrue($station->estActive(), 'La Station reste active independamment de sa Desserte.');

        $crawler = $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $form = $crawler->selectButton('Mettre à jour')->form();
        $form['raison[dessertes]']->setValue([(string) $desserte->getId()]);
        $this->client->submit($form);

        self::assertResponseRedirects('/raison');

        $this->manager->clear();
        $updatedDesserte = $this->manager->getRepository(Desserte::class)->find($desserte->getId());
        $updatedStation = $this->manager->getRepository(Station::class)->find($station->getId());

        self::assertCount(1, $updatedDesserte->getRaisons());
        self::assertFalse($updatedDesserte->estActive());
        self::assertTrue($updatedStation->estActive(), 'La Station ne doit pas devenir inactive par ricochet.');
    }

    public function testRemove(): void
    {
        $fixture = new Raison();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/raison');
        self::assertSame(0, $this->raisonRepository->count([]));
    }
}
