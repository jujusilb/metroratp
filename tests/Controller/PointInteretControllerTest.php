<?php

namespace App\Tests\Controller;

use App\Entity\PointInteret;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class PointInteretControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<PointInteret> $pointInteretRepository */
    private EntityRepository $pointInteretRepository;
    private string $path = '/point-interet/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->pointInteretRepository = $this->manager->getRepository(PointInteret::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains("Points d'intérêt");
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'point_interet[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/point-interet');

        self::assertSame(1, $this->pointInteretRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new PointInteret();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains("Point d'intérêt");
    }

    public function testShowAfficheLesStationsRattachees(): void
    {
        $station = new Station();
        $station->setLabel('Gambetta');
        $this->manager->persist($station);

        $fixture = new PointInteret();
        $fixture->setLabel('Père-Lachaise');
        $fixture->addStation($station);
        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('body', 'Gambetta');
    }

    public function testEdit(): void
    {
        $fixture = new PointInteret();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'point_interet[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/point-interet');

        $fixture = $this->pointInteretRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testEditAssigneDesStations(): void
    {
        $station = new Station();
        $station->setLabel('Châtelet - Les Halles');
        $this->manager->persist($station);

        $fixture = new PointInteret();
        $fixture->setLabel('Forum des Halles');
        $this->manager->persist($fixture);
        $this->manager->flush();

        $crawler = $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $form = $crawler->selectButton('Mettre à jour')->form();
        $form['point_interet[stations]']->setValue([(string) $station->getId()]);
        $this->client->submit($form);

        self::assertResponseRedirects('/point-interet');

        $this->manager->clear();
        $updated = $this->pointInteretRepository->find($fixture->getId());

        self::assertCount(1, $updated->getStations());
    }

    public function testRemove(): void
    {
        $fixture = new PointInteret();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/point-interet');
        self::assertSame(0, $this->pointInteretRepository->count([]));
    }
}
