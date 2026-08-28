<?php

namespace App\Tests\Controller;

use App\Entity\Depot;
use App\Entity\DepotLigne;
use App\Entity\Ligne;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class DepotLigneControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<DepotLigne> $depotLigneRepository */
    private EntityRepository $depotLigneRepository;
    private string $path = '/depot/ligne/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->depotLigneRepository = $this->manager->getRepository(DepotLigne::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    private function createDepot(): Depot
    {
        $depot = new Depot();
        $depot->setLabel('Centre bus de Testville');

        $this->manager->persist($depot);
        $this->manager->flush();

        return $depot;
    }

    private function createLigne(): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel('21');

        $this->manager->persist($ligne);
        $this->manager->flush();

        return $ligne;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Dépôt-Ligne');
    }

    public function testNew(): void
    {
        $depot = $this->createDepot();
        $ligne = $this->createLigne();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'depot_ligne[depot]' => $depot->getId(),
            'depot_ligne[ligne]' => $ligne->getId(),
        ]);

        self::assertResponseRedirects('/depot/ligne');

        self::assertSame(1, $this->depotLigneRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new DepotLigne();
        $fixture->setDepot($this->createDepot());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('DepotLigne');
    }

    public function testEdit(): void
    {
        $fixture = new DepotLigne();
        $fixture->setDepot($this->createDepot());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'depot_ligne[arrivee]' => '2019-04-20',
        ]);

        self::assertResponseRedirects('/depot/ligne');

        $fixture = $this->depotLigneRepository->findAll();

        self::assertSame('2019-04-20', $fixture[0]->getArrivee()->format('Y-m-d'));
    }

    public function testRemove(): void
    {
        $fixture = new DepotLigne();
        $fixture->setDepot($this->createDepot());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/depot/ligne');
        self::assertSame(0, $this->depotLigneRepository->count([]));
    }
}
