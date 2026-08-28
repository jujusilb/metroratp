<?php

namespace App\Tests\Controller;

use App\Entity\Depot;
use App\Entity\Materiel;
use App\Entity\MaterielDepot;
use App\Entity\TypeMateriel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class MaterielDepotControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<MaterielDepot> $materielDepotRepository */
    private EntityRepository $materielDepotRepository;
    private string $path = '/materiel/depot/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->materielDepotRepository = $this->manager->getRepository(MaterielDepot::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    private function createMateriel(): Materiel
    {
        $typeMateriel = new TypeMateriel();
        $typeMateriel->setLabel('ferraille');
        $this->manager->persist($typeMateriel);

        $materiel = new Materiel();
        $materiel->setLabel('Bus articulé');
        $materiel->setTypeMateriel($typeMateriel);
        $this->manager->persist($materiel);

        $this->manager->flush();

        return $materiel;
    }

    private function createDepot(): Depot
    {
        $depot = new Depot();
        $depot->setLabel('Centre bus de Testville');

        $this->manager->persist($depot);
        $this->manager->flush();

        return $depot;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Matériel-Dépôt');
    }

    public function testNew(): void
    {
        $materiel = $this->createMateriel();
        $depot = $this->createDepot();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'materiel_depot[materiel]' => $materiel->getId(),
            'materiel_depot[depot]' => $depot->getId(),
        ]);

        self::assertResponseRedirects('/materiel/depot');

        self::assertSame(1, $this->materielDepotRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new MaterielDepot();
        $fixture->setMateriel($this->createMateriel());
        $fixture->setDepot($this->createDepot());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('MaterielDepot');
    }

    public function testEdit(): void
    {
        $fixture = new MaterielDepot();
        $fixture->setMateriel($this->createMateriel());
        $fixture->setDepot($this->createDepot());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'materiel_depot[arrivee]' => '2019-04-20',
        ]);

        self::assertResponseRedirects('/materiel/depot');

        $fixture = $this->materielDepotRepository->findAll();

        self::assertSame('2019-04-20', $fixture[0]->getArrivee()->format('Y-m-d'));
    }

    public function testRemove(): void
    {
        $fixture = new MaterielDepot();
        $fixture->setMateriel($this->createMateriel());
        $fixture->setDepot($this->createDepot());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/materiel/depot');
        self::assertSame(0, $this->materielDepotRepository->count([]));
    }
}
