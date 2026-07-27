<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\StyleStation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class StyleStationControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<StyleStation> $styleStationRepository */
    private EntityRepository $styleStationRepository;
    private string $path = '/style/station/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->styleStationRepository = $this->manager->getRepository(StyleStation::class);
        $this->resetDatabase($this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Style de station');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'style_station[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/style/station');

        self::assertSame(1, $this->styleStationRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new StyleStation();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('StyleStation');
    }

    public function testEdit(): void
    {
        $fixture = new StyleStation();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'style_station[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/style/station');

        $fixture = $this->styleStationRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testEditAssigneDesDessertes(): void
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);

        $stationA = new Station();
        $stationA->setLabel('Châtelet');
        $this->manager->persist($stationA);

        $stationB = new Station();
        $stationB->setLabel('Bastille');
        $this->manager->persist($stationB);

        $desserteA = new Desserte();
        $desserteA->setLigne($ligne);
        $desserteA->setStation($stationA);
        $this->manager->persist($desserteA);

        $desserteB = new Desserte();
        $desserteB->setLigne($ligne);
        $desserteB->setStation($stationB);
        $this->manager->persist($desserteB);

        $fixture = new StyleStation();
        $fixture->setLabel('Andreu-Motte');
        $this->manager->persist($fixture);
        $this->manager->flush();

        $crawler = $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $form = $crawler->selectButton('Mettre à jour')->form();
        $form['style_station[dessertes]']->setValue([(string) $desserteA->getId(), (string) $desserteB->getId()]);
        $this->client->submit($form);

        self::assertResponseRedirects('/style/station');

        $this->manager->clear();
        $updated = $this->styleStationRepository->find($fixture->getId());

        self::assertCount(2, $updated->getDessertes());
    }

    public function testRemove(): void
    {
        $fixture = new StyleStation();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/style/station');
        self::assertSame(0, $this->styleStationRepository->count([]));
    }
}
