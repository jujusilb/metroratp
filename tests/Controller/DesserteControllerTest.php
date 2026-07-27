<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class DesserteControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Desserte> $desserteRepository */
    private EntityRepository $desserteRepository;
    private string $path = '/desserte/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->desserteRepository = $this->manager->getRepository(Desserte::class);
        $this->resetDatabase($this->manager);
    }

    private function createStation(): Station
    {
        $station = new Station();
        $station->setLabel('Châtelet');

        $this->manager->persist($station);
        $this->manager->flush();

        return $station;
    }

    private function createLigne(): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');

        $this->manager->persist($ligne);
        $this->manager->flush();

        return $ligne;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Desserte');
    }

    public function testNew(): void
    {
        $station = $this->createStation();
        $ligne = $this->createLigne();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'desserte[station]' => $station->getId(),
            'desserte[ligne]' => $ligne->getId(),
        ]);

        self::assertResponseRedirects('/desserte');

        self::assertSame(1, $this->desserteRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Desserte();
        $fixture->setStation($this->createStation());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Desserte');
    }

    public function testEdit(): void
    {
        $fixture = new Desserte();
        $fixture->setStation($this->createStation());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $newStation = $this->createStation();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'desserte[station]' => $newStation->getId(),
        ]);

        self::assertResponseRedirects('/desserte');

        $fixture = $this->desserteRepository->findAll();

        self::assertSame($newStation->getId(), $fixture[0]->getStation()->getId());
    }

    public function testRemove(): void
    {
        $fixture = new Desserte();
        $fixture->setStation($this->createStation());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/desserte');
        self::assertSame(0, $this->desserteRepository->count([]));
    }
}
