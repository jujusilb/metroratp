<?php

namespace App\Tests\Controller;

use App\Entity\Station;
use App\Entity\Ville;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class VilleControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Ville> $villeRepository */
    private EntityRepository $villeRepository;
    private string $path = '/ville/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->villeRepository = $this->manager->getRepository(Ville::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Villes');
    }

    public function testShow(): void
    {
        $ville = new Ville();
        $ville->setLabel('Testville');
        $ville->setCodeInsee('99999');
        $this->manager->persist($ville);

        $station = new Station();
        $station->setLabel('Gare de test');
        $ville->addStation($station);
        $this->manager->persist($station);

        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $ville->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Testville');
        self::assertSelectorTextContains('body', 'Gare de test');
    }
}
