<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class TronconDesserteControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<TronconDesserte> $tronconDesserteRepository */
    private EntityRepository $tronconDesserteRepository;
    private string $path = '/troncon-desserte/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->tronconDesserteRepository = $this->manager->getRepository(TronconDesserte::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    private function createTypeDesserte(string $label): TypeDesserte
    {
        $typeDesserte = new TypeDesserte();
        $typeDesserte->setLabel($label);
        $this->manager->persist($typeDesserte);

        return $typeDesserte;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Tronçons-dessertes');
    }

    public function testNewSansTronconRedirigeVersLaListeDesTroncons(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseRedirects('/troncon');
    }

    public function testNewSurUnTronconNeufUtiliseUnChampId(): void
    {
        // Aucune TronconDesserte encore : aucune Ligne deductible, le champ desserte doit etre un
        // simple id (pas un <select>, voir TronconDesserteType).
        $troncon = new Troncon();
        $this->manager->persist($troncon);
        $this->manager->flush();

        $crawler = $this->client->request('GET', sprintf('%snew?troncon=%s', $this->path, $troncon->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertCount(0, $crawler->filter('select[name="troncon_desserte[desserte]"]'));
        self::assertCount(1, $crawler->filter('input[name="troncon_desserte[desserte]"]'));
    }

    public function testNewSurUnTronconExistantNeProposeQueLesDessertesDeLaLigne(): void
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);

        $autreLigne = new Ligne();
        $autreLigne->setLabel('2');
        $this->manager->persist($autreLigne);

        $stationA = new Station();
        $stationA->setLabel('A');
        $this->manager->persist($stationA);
        $desserteA = new Desserte();
        $desserteA->setLigne($ligne);
        $desserteA->setStation($stationA);
        $this->manager->persist($desserteA);

        $stationB = new Station();
        $stationB->setLabel('B (autre ligne)');
        $this->manager->persist($stationB);
        $desserteB = new Desserte();
        $desserteB->setLigne($autreLigne);
        $desserteB->setStation($stationB);
        $this->manager->persist($desserteB);

        $stationC = new Station();
        $stationC->setLabel('C (meme ligne)');
        $this->manager->persist($stationC);
        $desserteC = new Desserte();
        $desserteC->setLigne($ligne);
        $desserteC->setStation($stationC);
        $this->manager->persist($desserteC);

        $troncon = new Troncon();
        $this->manager->persist($troncon);

        $typeDepart = $this->createTypeDesserte('Départ');
        $typeArrivee = $this->createTypeDesserte('Arrivée');
        $existante = new TronconDesserte();
        $existante->setTroncon($troncon);
        $existante->setDesserte($desserteA);
        $existante->setTypeDesserte($typeDepart);
        $this->manager->persist($existante);

        $this->manager->flush();
        // Sans ca, $troncon->getTronconDessertes() resterait vide en memoire (setTroncon() ne
        // synchronise pas la collection inverse) : le controleur relirait le meme objet perime
        // depuis l'identity map plutot qu'une vraie requete.
        $this->manager->clear();
        $troncon = $this->manager->getRepository(Troncon::class)->find($troncon->getId());

        $crawler = $this->client->request('GET', sprintf('%snew?troncon=%s', $this->path, $troncon->getId()));

        self::assertResponseStatusCodeSame(200);
        $options = $crawler->filter('select[name="troncon_desserte[desserte]"] option')->each(fn ($node) => $node->text());
        self::assertContains('C (meme ligne)', $options);
        self::assertNotContains('B (autre ligne)', $options, 'Une desserte d\'une autre ligne ne doit pas apparaitre.');

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['troncon_desserte[desserte]'] = (string) $desserteC->getId();
        $form['troncon_desserte[typeDesserte]'] = (string) $typeArrivee->getId();
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/troncon/%s', $troncon->getId()));
        self::assertSame(2, $this->tronconDesserteRepository->count([]));
    }

    public function testRemove(): void
    {
        $troncon = new Troncon();
        $this->manager->persist($troncon);
        $station = new Station();
        $station->setLabel('A');
        $this->manager->persist($station);
        $desserte = new Desserte();
        $desserte->setStation($station);
        $this->manager->persist($desserte);
        $type = $this->createTypeDesserte('Départ');

        $fixture = new TronconDesserte();
        $fixture->setTroncon($troncon);
        $fixture->setDesserte($desserte);
        $fixture->setTypeDesserte($type);
        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects(sprintf('/troncon/%s', $troncon->getId()));
        self::assertSame(0, $this->tronconDesserteRepository->count([]));
    }
}
