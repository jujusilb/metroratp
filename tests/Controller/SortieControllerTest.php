<?php

namespace App\Tests\Controller;

use App\Entity\Acces;
use App\Entity\Sortie;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class SortieControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Sortie> $sortieRepository */
    private EntityRepository $sortieRepository;
    private string $path = '/sortie/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->sortieRepository = $this->manager->getRepository(Sortie::class);
        $this->resetDatabase($this->manager);
    }

    private function createAcces(): Acces
    {
        $acces = new Acces();
        $acces->setLabel('Sortie 1');
        $acces->setNumero('1');
        $acces->setIsAccessible(true);

        $this->manager->persist($acces);
        $this->manager->flush();

        return $acces;
    }

    private function createStation(): Station
    {
        $station = new Station();
        $station->setLabel('Châtelet');

        $this->manager->persist($station);
        $this->manager->flush();

        return $station;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Sortie');
    }

    public function testNew(): void
    {
        $acces = $this->createAcces();
        $station = $this->createStation();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'sortie[acces]' => $acces->getId(),
            'sortie[station]' => $station->getId(),
        ]);

        self::assertResponseRedirects('/sortie');

        self::assertSame(1, $this->sortieRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Sortie();
        $fixture->setAcces($this->createAcces());
        $fixture->setStation($this->createStation());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Sortie');
    }

    public function testEdit(): void
    {
        $fixture = new Sortie();
        $fixture->setAcces($this->createAcces());
        $fixture->setStation($this->createStation());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $newStation = $this->createStation();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'sortie[station]' => $newStation->getId(),
        ]);

        self::assertResponseRedirects('/sortie');

        $fixture = $this->sortieRepository->findAll();

        self::assertSame($newStation->getId(), $fixture[0]->getStation()->getId());
    }

    public function testRemove(): void
    {
        $fixture = new Sortie();
        $fixture->setAcces($this->createAcces());
        $fixture->setStation($this->createStation());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/sortie');
        self::assertSame(0, $this->sortieRepository->count([]));
    }
}
