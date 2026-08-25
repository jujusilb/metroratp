<?php

namespace App\Tests\Controller;

use App\Entity\Automatisation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AutomatisationControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Automatisation> $automatisationRepository */
    private EntityRepository $automatisationRepository;
    private string $path = '/automatisation/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->automatisationRepository = $this->manager->getRepository(Automatisation::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Automatisation');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'automatisation[label]' => 'Porte de rame',
        ]);

        self::assertResponseRedirects('/automatisation');

        self::assertSame(1, $this->automatisationRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Automatisation();
        $fixture->setLabel('Porte palière');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Automatisation');
    }

    public function testEdit(): void
    {
        $fixture = new Automatisation();
        $fixture->setLabel('Total');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'automatisation[label]' => 'Totale (sans conducteur)',
        ]);

        self::assertResponseRedirects('/automatisation');

        $fixture = $this->automatisationRepository->findAll();

        self::assertSame('Totale (sans conducteur)', $fixture[0]->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new Automatisation();
        $fixture->setLabel('Porte de rame');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/automatisation');
        self::assertSame(0, $this->automatisationRepository->count([]));
    }
}
