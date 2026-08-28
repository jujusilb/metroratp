<?php

namespace App\Tests\Controller;

use App\Entity\Depot;
use App\Entity\DepotGestionnaire;
use App\Entity\Gestionnaire;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class DepotGestionnaireControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<DepotGestionnaire> $depotGestionnaireRepository */
    private EntityRepository $depotGestionnaireRepository;
    private string $path = '/depot/gestionnaire/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->depotGestionnaireRepository = $this->manager->getRepository(DepotGestionnaire::class);
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

    private function createGestionnaire(): Gestionnaire
    {
        $gestionnaire = new Gestionnaire();
        $gestionnaire->setLabel('Keolis Test');

        $this->manager->persist($gestionnaire);
        $this->manager->flush();

        return $gestionnaire;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Dépôt-Gestionnaire');
    }

    public function testNew(): void
    {
        $depot = $this->createDepot();
        $gestionnaire = $this->createGestionnaire();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'depot_gestionnaire[depot]' => $depot->getId(),
            'depot_gestionnaire[gestionnaire]' => $gestionnaire->getId(),
        ]);

        self::assertResponseRedirects('/depot/gestionnaire');

        self::assertSame(1, $this->depotGestionnaireRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new DepotGestionnaire();
        $fixture->setDepot($this->createDepot());
        $fixture->setGestionnaire($this->createGestionnaire());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('DepotGestionnaire');
    }

    public function testEdit(): void
    {
        $fixture = new DepotGestionnaire();
        $fixture->setDepot($this->createDepot());
        $fixture->setGestionnaire($this->createGestionnaire());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'depot_gestionnaire[arrivee]' => '2019-04-20',
        ]);

        self::assertResponseRedirects('/depot/gestionnaire');

        $fixture = $this->depotGestionnaireRepository->findAll();

        self::assertSame('2019-04-20', $fixture[0]->getArrivee()->format('Y-m-d'));
    }

    public function testRemove(): void
    {
        $fixture = new DepotGestionnaire();
        $fixture->setDepot($this->createDepot());
        $fixture->setGestionnaire($this->createGestionnaire());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/depot/gestionnaire');
        self::assertSame(0, $this->depotGestionnaireRepository->count([]));
    }
}
