<?php

namespace App\Tests\Controller;

use App\Entity\Ligne;
use App\Repository\LigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class LigneControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Ligne> $ligneRepository */
    private EntityRepository $ligneRepository;
    private string $path = '/ligne/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->ligneRepository = $this->manager->getRepository(Ligne::class);
        $this->resetDatabase($this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Ligne');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'ligne[label]' => '1',
        ]);

        self::assertResponseRedirects('/ligne');

        self::assertSame(1, $this->ligneRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Ligne();
        $fixture->setLabel('1');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Ligne');
    }

    public function testEdit(): void
    {
        $fixture = new Ligne();
        $fixture->setLabel('1');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'ligne[label]' => '14',
        ]);

        self::assertResponseRedirects('/ligne');

        $fixture = $this->ligneRepository->findAll();

        self::assertSame('14', $fixture[0]->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new Ligne();
        $fixture->setLabel('1');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/ligne');
        self::assertSame(0, $this->ligneRepository->count([]));
    }
}
