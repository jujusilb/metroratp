<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Raison;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class RaisonControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Raison> $raisonRepository */
    private EntityRepository $raisonRepository;
    private string $path = '/raison/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->raisonRepository = $this->manager->getRepository(Raison::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains("Raisons d'inactivité");
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'raison[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/raison');

        self::assertSame(1, $this->raisonRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Raison();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Raison');
    }

    public function testEdit(): void
    {
        $fixture = new Raison();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'raison[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/raison');

        $fixture = $this->raisonRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    public function testEditAssigneDesDessertesEtMarqueInactive(): void
    {
        $ligneMetro = new Ligne();
        $ligneMetro->setLabel('5');
        $this->manager->persist($ligneMetro);

        // 2e Desserte reelle (ex: un bus) sur la meme Station : demontre que la Station reste
        // active tant qu'AU MOINS UNE de ses Desserte l'est, meme si celle du metro devient morte
        // (cas reel "stations fantomes" - Martin Nadaud/Porte Molitor/Haxo, voir TODO.md).
        $ligneBus = new Ligne();
        $ligneBus->setLabel('61');
        $this->manager->persist($ligneBus);

        $station = new Station();
        $station->setLabel('Arsenal');
        $this->manager->persist($station);

        $desserteMetro = new Desserte();
        $desserteMetro->setStation($station);
        $desserteMetro->setLigne($ligneMetro);
        $this->manager->persist($desserteMetro);

        $desserteBus = new Desserte();
        $desserteBus->setStation($station);
        $desserteBus->setLigne($ligneBus);
        $this->manager->persist($desserteBus);

        // Le champ raison[dessertes] ne liste que les Desserte DEJA taguees par une Raison
        // (voir RaisonType : le reseau complet est bien trop volumineux pour un simple
        // <select multiple>) - on tague donc desserteMetro une premiere fois directement pour
        // qu'elle apparaisse dans la liste, avant de la reassigner via le formulaire.
        $raisonInitiale = new Raison();
        $raisonInitiale->setLabel('Raison initiale');
        $raisonInitiale->addDesserte($desserteMetro);
        $this->manager->persist($raisonInitiale);

        $fixture = new Raison();
        $fixture->setLabel('Fermée pour la guerre');
        $this->manager->persist($fixture);
        $this->manager->flush();

        $crawler = $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $form = $crawler->selectButton('Mettre à jour')->form();
        $form['raison[dessertes]']->setValue([(string) $desserteMetro->getId()]);
        $this->client->submit($form);

        self::assertResponseRedirects('/raison');

        $this->manager->clear();
        $updatedDesserte = $this->manager->getRepository(Desserte::class)->find($desserteMetro->getId());
        $updatedStation = $this->manager->getRepository(Station::class)->find($station->getId());

        self::assertCount(2, $updatedDesserte->getRaisons(), 'La Raison initiale et la nouvelle doivent toutes deux etre rattachees.');
        self::assertFalse($updatedDesserte->estActive());
        self::assertTrue($updatedStation->estActive(), 'La Station ne doit pas devenir inactive par ricochet.');
    }

    public function testRemove(): void
    {
        $fixture = new Raison();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/raison');
        self::assertSame(0, $this->raisonRepository->count([]));
    }
}
