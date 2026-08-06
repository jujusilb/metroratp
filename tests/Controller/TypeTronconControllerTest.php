<?php

namespace App\Tests\Controller;

use App\Entity\TypeTroncon;
use App\Repository\TypeTronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class TypeTronconControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<TypeTroncon> $typeTronconRepository */
    private EntityRepository $typeTronconRepository;
    private string $path = '/type/troncon/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->typeTronconRepository = $this->manager->getRepository(TypeTroncon::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Type de tronçon');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'type_troncon[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/type/troncon');

        self::assertSame(1, $this->typeTronconRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new TypeTroncon();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('TypeTroncon');
    }

    public function testEdit(): void
    {
        $fixture = new TypeTroncon();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'type_troncon[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/type/troncon');

        $fixture = $this->typeTronconRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new TypeTroncon();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/type/troncon');
        self::assertSame(0, $this->typeTronconRepository->count([]));
    }
}
