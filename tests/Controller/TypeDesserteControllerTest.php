<?php

namespace App\Tests\Controller;

use App\Entity\TypeDesserte;
use App\Repository\TypeDesserteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class TypeDesserteControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<TypeDesserte> $typeDesserteRepository */
    private EntityRepository $typeDesserteRepository;
    private string $path = '/type/desserte/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->typeDesserteRepository = $this->manager->getRepository(TypeDesserte::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Types de desserte');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'type_desserte[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/type/desserte');

        self::assertSame(1, $this->typeDesserteRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new TypeDesserte();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Type de desserte');
    }

    public function testEdit(): void
    {
        $fixture = new TypeDesserte();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'type_desserte[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/type/desserte');

        $fixture = $this->typeDesserteRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new TypeDesserte();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/type/desserte');
        self::assertSame(0, $this->typeDesserteRepository->count([]));
    }

    public function testLeLabelDepartEstVerrouilleALedition(): void
    {
        $fixture = new TypeDesserte();
        $fixture->setLabel('Départ');
        $this->manager->persist($fixture);
        $this->manager->flush();

        $crawler = $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        self::assertTrue($crawler->filter('input[name="type_desserte[label]"]')->attr('disabled') !== null);
    }

    public function testImpossibleDeSupprimerDepart(): void
    {
        $fixture = new TypeDesserte();
        $fixture->setLabel('Départ');
        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects(sprintf('/type/desserte/%s', $fixture->getId()));
        self::assertSame(1, $this->typeDesserteRepository->count([]), 'Départ ne doit pas etre supprimable (verrouille).');
    }
}
