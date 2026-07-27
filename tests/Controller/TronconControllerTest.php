<?php

namespace App\Tests\Controller;

use App\Entity\Troncon;
use App\Entity\TypeTroncon;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class TronconControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Troncon> $tronconRepository */
    private EntityRepository $tronconRepository;
    private string $path = '/troncon/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->tronconRepository = $this->manager->getRepository(Troncon::class);
        $this->resetDatabase($this->manager);
    }

    private function createTypeTroncon(): TypeTroncon
    {
        $typeTroncon = new TypeTroncon();
        $typeTroncon->setLabel('Intérieur');

        $this->manager->persist($typeTroncon);
        $this->manager->flush();

        return $typeTroncon;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Tronçon');
    }

    public function testNew(): void
    {
        $typeTroncon = $this->createTypeTroncon();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'troncon[parcours]' => 'Testing',
            'troncon[typeTroncon]' => $typeTroncon->getId(),
        ]);

        self::assertResponseRedirects('/troncon');

        self::assertSame(1, $this->tronconRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Troncon();
        $fixture->setParcours('My Title');
        $fixture->setTypeTroncon($this->createTypeTroncon());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Troncon');
    }

    public function testEdit(): void
    {
        $fixture = new Troncon();
        $fixture->setParcours('Value');
        $fixture->setTypeTroncon($this->createTypeTroncon());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'troncon[parcours]' => 'Something New',
        ]);

        self::assertResponseRedirects('/troncon');

        $fixture = $this->tronconRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getParcours());
    }

    public function testRemove(): void
    {
        $fixture = new Troncon();
        $fixture->setParcours('Value');
        $fixture->setTypeTroncon($this->createTypeTroncon());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/troncon');
        self::assertSame(0, $this->tronconRepository->count([]));
    }
}
