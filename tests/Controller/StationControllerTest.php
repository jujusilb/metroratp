<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Raison;
use App\Entity\Station;
use App\Repository\StationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class StationControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Station> $stationRepository */
    private EntityRepository $stationRepository;
    private string $path = '/station/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->stationRepository = $this->manager->getRepository(Station::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Station');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'station[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/station');

        self::assertSame(1, $this->stationRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Station();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Station');
    }

    public function testEdit(): void
    {
        $fixture = new Station();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'station[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/station');

        $fixture = $this->stationRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testFiltreStatutActifEtInactif(): void
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);

        // Sans aucune desserte du tout : inactive par construction (rien ne la dessert).
        $sansDesserte = new Station();
        $sansDesserte->setLabel('Sans desserte');
        $this->manager->persist($sansDesserte);

        // Une seule desserte, marquee inactive via Raison : inactive.
        $stationMorte = new Station();
        $stationMorte->setLabel('Station morte');
        $this->manager->persist($stationMorte);
        $desserteMorte = new Desserte();
        $desserteMorte->setStation($stationMorte);
        $desserteMorte->setLigne($ligne);
        $this->manager->persist($desserteMorte);

        // 2 dessertes, une seule inactive : la Station reste active (au moins une l'est).
        $stationMixte = new Station();
        $stationMixte->setLabel('Station mixte');
        $this->manager->persist($stationMixte);
        $desserteMixteMorte = new Desserte();
        $desserteMixteMorte->setStation($stationMixte);
        $desserteMixteMorte->setLigne($ligne);
        $this->manager->persist($desserteMixteMorte);
        $desserteMixteVivante = new Desserte();
        $desserteMixteVivante->setStation($stationMixte);
        $this->manager->persist($desserteMixteVivante);

        $raison = new Raison();
        $raison->setLabel('Fermée pour la guerre');
        $raison->addDesserte($desserteMorte);
        $raison->addDesserte($desserteMixteMorte);
        $this->manager->persist($raison);

        $this->manager->flush();

        $this->client->request('GET', '/station?inactif=0');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('body', 'Station mixte');
        self::assertSelectorTextNotContains('body', 'Sans desserte');
        self::assertSelectorTextNotContains('body', 'Station morte');

        $this->client->request('GET', '/station?actif=0');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('body', 'Sans desserte');
        self::assertSelectorTextContains('body', 'Station morte');
        self::assertSelectorTextNotContains('body', 'Station mixte');
    }

    public function testRemove(): void
    {
        $fixture = new Station();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/station');
        self::assertSame(0, $this->stationRepository->count([]));
    }
}
