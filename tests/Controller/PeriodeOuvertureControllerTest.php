<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\PeriodeOuverture;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class PeriodeOuvertureControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<PeriodeOuverture> $periodeOuvertureRepository */
    private EntityRepository $periodeOuvertureRepository;
    private string $path = '/periode/ouverture/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->periodeOuvertureRepository = $this->manager->getRepository(PeriodeOuverture::class);
        $this->resetDatabase($this->manager);
    }

    private function createDesserte(): Desserte
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);

        $station = new Station();
        $station->setLabel('Châtelet');
        $this->manager->persist($station);

        $desserte = new Desserte();
        $desserte->setLigne($ligne);
        $desserte->setStation($station);
        $this->manager->persist($desserte);

        $this->manager->flush();

        return $desserte;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains("Périodes d'ouverture");
    }

    public function testNew(): void
    {
        $desserte = $this->createDesserte();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'periode_ouverture[desserte]' => $desserte->getId(),
            'periode_ouverture[ordre]' => '1',
            'periode_ouverture[ouverture]' => '1900-01-01',
        ]);

        self::assertResponseRedirects(sprintf('/desserte/%d', $desserte->getId()));

        self::assertSame(1, $this->periodeOuvertureRepository->count([]));
    }

    public function testNewPreremplitLaDesserteEtLOrdreDepuisLaQueryString(): void
    {
        $desserte = $this->createDesserte();

        $existante = new PeriodeOuverture();
        $existante->setDesserte($desserte);
        $existante->setOrdre(1);
        $existante->setOuverture(new \DateTime('1900-01-01'));
        $existante->setFermeture(new \DateTime('1939-09-01'));
        $this->manager->persist($existante);
        $this->manager->flush();
        // Sans clear(), la collection inverse Desserte::periodesOuverture reste vide en memoire
        // (Doctrine ne la synchronise pas automatiquement) : une vraie requete HTTP repartirait
        // toujours d'un EntityManager frais et la rechargerait correctement depuis la base.
        $this->manager->clear();

        $crawler = $this->client->request('GET', sprintf('%snew?desserte=%d', $this->path, $desserte->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertSame((string) $desserte->getId(), $crawler->filter('#periode_ouverture_desserte option[selected]')->attr('value'));
        self::assertSame('2', $crawler->filter('#periode_ouverture_ordre')->attr('value'));
    }

    public function testShow(): void
    {
        $fixture = new PeriodeOuverture();
        $fixture->setDesserte($this->createDesserte());
        $fixture->setOrdre(1);
        $fixture->setOuverture(new \DateTime('1900-01-01'));

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains("Période d'ouverture");
    }

    public function testEdit(): void
    {
        $fixture = new PeriodeOuverture();
        $fixture->setDesserte($this->createDesserte());
        $fixture->setOrdre(1);
        $fixture->setOuverture(new \DateTime('1900-01-01'));

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'periode_ouverture[fermeture]' => '1939-09-01',
        ]);

        self::assertResponseRedirects('/periode/ouverture');

        $updated = $this->periodeOuvertureRepository->find($fixture->getId());

        self::assertSame('1939-09-01', $updated->getFermeture()->format('Y-m-d'));
    }

    public function testRemove(): void
    {
        $fixture = new PeriodeOuverture();
        $fixture->setDesserte($this->createDesserte());
        $fixture->setOrdre(1);
        $fixture->setOuverture(new \DateTime('1900-01-01'));

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/periode/ouverture');
        self::assertSame(0, $this->periodeOuvertureRepository->count([]));
    }
}
