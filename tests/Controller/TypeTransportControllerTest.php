<?php

namespace App\Tests\Controller;

use App\Entity\TypeTransport;
use App\Repository\TypeTransportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class TypeTransportControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<TypeTransport> $typeTransportRepository */
    private EntityRepository $typeTransportRepository;
    private string $path = '/type/transport/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->typeTransportRepository = $this->manager->getRepository(TypeTransport::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Types de transport');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'type_transport[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/type/transport');

        self::assertSame(1, $this->typeTransportRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new TypeTransport();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Type de transport');
    }

    public function testEdit(): void
    {
        $fixture = new TypeTransport();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'type_transport[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/type/transport');

        $fixture = $this->typeTransportRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new TypeTransport();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/type/transport');
        self::assertSame(0, $this->typeTransportRepository->count([]));
    }
}
