<?php

namespace App\Tests\Controller;

use App\Entity\Depot;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class DepotControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Depot> $depotRepository */
    private EntityRepository $depotRepository;
    private string $path = '/depot/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->depotRepository = $this->manager->getRepository(Depot::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Dépôts');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'depot[label]' => 'Centre bus de Testville',
            'depot[adresse]' => '1 rue du Test',
        ]);

        self::assertResponseRedirects('/depot');

        self::assertSame(1, $this->depotRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Depot();
        $fixture->setLabel('Centre bus de Testville');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Dépôt');
    }

    public function testEdit(): void
    {
        $fixture = new Depot();
        $fixture->setLabel('Centre bus de Testville');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'depot[label]' => 'Centre bus renommé',
        ]);

        self::assertResponseRedirects('/depot');

        $fixture = $this->depotRepository->findAll();

        self::assertSame('Centre bus renommé', $fixture[0]->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new Depot();
        $fixture->setLabel('Centre bus de Testville');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/depot');
        self::assertSame(0, $this->depotRepository->count([]));
    }
}
