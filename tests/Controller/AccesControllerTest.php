<?php

namespace App\Tests\Controller;

use App\Entity\Acces;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AccesControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Acces> $accesRepository */
    private EntityRepository $accesRepository;
    private string $path = '/acces/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->accesRepository = $this->manager->getRepository(Acces::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Accès');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'acces[label]' => 'Sortie 1',
            'acces[numero]' => 'A1',
            'acces[isAccessible]' => true,
        ]);

        self::assertResponseRedirects('/acces');

        self::assertSame(1, $this->accesRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Acces();
        $fixture->setLabel('My Title');
        $fixture->setNumero('A1');
        $fixture->setIsAccessible(true);

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Acces');
    }

    public function testEdit(): void
    {
        $fixture = new Acces();
        $fixture->setLabel('Value');
        $fixture->setNumero('A1');
        $fixture->setIsAccessible(true);

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'acces[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/acces');

        $fixture = $this->accesRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new Acces();
        $fixture->setLabel('Value');
        $fixture->setNumero('A1');
        $fixture->setIsAccessible(true);

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/acces');
        self::assertSame(0, $this->accesRepository->count([]));
    }
}
