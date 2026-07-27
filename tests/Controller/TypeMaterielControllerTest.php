<?php

namespace App\Tests\Controller;

use App\Entity\TypeMateriel;
use App\Repository\TypeMaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class TypeMaterielControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<TypeMateriel> $typeMaterielRepository */
    private EntityRepository $typeMaterielRepository;
    private string $path = '/type/materiel/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->typeMaterielRepository = $this->manager->getRepository(TypeMateriel::class);
        $this->resetDatabase($this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Type de matériel');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'type_materiel[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/type/materiel');

        self::assertSame(1, $this->typeMaterielRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new TypeMateriel();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('TypeMateriel');
    }

    public function testEdit(): void
    {
        $fixture = new TypeMateriel();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'type_materiel[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/type/materiel');

        $fixture = $this->typeMaterielRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new TypeMateriel();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/type/materiel');
        self::assertSame(0, $this->typeMaterielRepository->count([]));
    }
}
