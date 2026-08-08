<?php

namespace App\Tests\Controller;

use App\Entity\Gestionnaire;
use App\Repository\GestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class GestionnaireControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Gestionnaire> $gestionnaireRepository */
    private EntityRepository $gestionnaireRepository;
    private string $path = '/gestionnaire/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->gestionnaireRepository = $this->manager->getRepository(Gestionnaire::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Gestionnaires');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'gestionnaire[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/gestionnaire');

        self::assertSame(1, $this->gestionnaireRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Gestionnaire();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Gestionnaire');
    }

    public function testEdit(): void
    {
        $fixture = new Gestionnaire();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'gestionnaire[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/gestionnaire');

        $fixture = $this->gestionnaireRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new Gestionnaire();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/gestionnaire');
        self::assertSame(0, $this->gestionnaireRepository->count([]));
    }
}
