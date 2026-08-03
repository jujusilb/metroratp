<?php

namespace App\Tests\Controller;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use App\Entity\Direction;
use App\Entity\Ligne;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class CorrespondanceControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Correspondance> $correspondanceRepository */
    private EntityRepository $correspondanceRepository;
    private string $path = '/correspondance/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->correspondanceRepository = $this->manager->getRepository(Correspondance::class);
        $this->resetDatabase($this->manager);
    }

    private function createDesserte(string $stationLabel): Desserte
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);

        $station = new Station();
        $station->setLabel($stationLabel);
        $this->manager->persist($station);

        $desserte = new Desserte();
        $desserte->setLigne($ligne);
        $desserte->setStation($station);
        $this->manager->persist($desserte);

        $this->manager->flush();

        return $desserte;
    }

    private function createDesserteSurLigne(Ligne $ligne, string $stationLabel): Desserte
    {
        $station = new Station();
        $station->setLabel($stationLabel);
        $this->manager->persist($station);

        $desserte = new Desserte();
        $desserte->setLigne($ligne);
        $desserte->setStation($station);
        $this->manager->persist($desserte);

        return $desserte;
    }

    private function createDirection(Ligne $ligne, Desserte $desserteTerminus): Direction
    {
        $direction = new Direction();
        $direction->setLigne($ligne);
        $direction->setDesserteTerminus($desserteTerminus);
        $this->manager->persist($direction);

        return $direction;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Correspondances');
    }

    public function testNew(): void
    {
        $desserteA = $this->createDesserte('Châtelet');
        $desserteB = $this->createDesserte('Les Halles');

        $crawler = $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['correspondance[desserteA]'] = (string) $desserteA->getId();
        $form['correspondance[desserteB]'] = (string) $desserteB->getId();
        $form['correspondance[distance]'] = '250';
        $this->client->submit($form);

        self::assertResponseRedirects('/correspondance');

        self::assertSame(1, $this->correspondanceRepository->count([]));
    }

    public function testNewNormaliseLOrdreDeLaPaire(): void
    {
        // On cree explicitement la desserte au plus grand id en premier (A), pour verifier
        // que l'entite la remet bien en position B (ordre canonique id croissant) avant
        // persistance, quel que soit l'ordre choisi dans le formulaire.
        // Les id auto-incrementes croissent dans l'ordre de creation : $dessertePetitId a
        // donc forcement le plus petit id des deux.
        $dessertePetitId = $this->createDesserte('Bastille');
        $desserteGrandId = $this->createDesserte('Nation');

        self::assertGreaterThan($dessertePetitId->getId(), $desserteGrandId->getId());

        $crawler = $this->client->request('GET', sprintf('%snew', $this->path));

        // Soumis volontairement dans l'ordre inverse (grand id en A) pour verifier que
        // l'entite les remet dans l'ordre canonique avant persistance.
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['correspondance[desserteA]'] = (string) $desserteGrandId->getId();
        $form['correspondance[desserteB]'] = (string) $dessertePetitId->getId();
        $this->client->submit($form);

        self::assertResponseRedirects('/correspondance');

        $fixture = $this->correspondanceRepository->findAll()[0];

        self::assertSame($dessertePetitId->getId(), $fixture->getDesserteA()->getId());
        self::assertSame($desserteGrandId->getId(), $fixture->getDesserteB()->getId());
    }

    public function testNewRefuseLesDeuxDessertesIdentiques(): void
    {
        $desserte = $this->createDesserte('Châtelet');

        $crawler = $this->client->request('GET', sprintf('%snew', $this->path));

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['correspondance[desserteA]'] = (string) $desserte->getId();
        $form['correspondance[desserteB]'] = (string) $desserte->getId();
        $this->client->submit($form);

        // Symfony renvoie 422 (pas 200) pour un formulaire resoumis avec des erreurs de
        // validation.
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->correspondanceRepository->count([]));
    }

    public function testDeuxCorrespondancesAvecDirectionsDifferentesPourLaMemePaire(): void
    {
        // Reproduit le cas Chatelet 4<->14 : la meme paire de dessertes peut avoir plusieurs
        // correspondances, une par combinaison de directions, avec des distances differentes.
        $ligne4 = new Ligne();
        $ligne4->setLabel('4');
        $this->manager->persist($ligne4);

        $ligne14 = new Ligne();
        $ligne14->setLabel('14');
        $this->manager->persist($ligne14);

        $chatelet4 = $this->createDesserteSurLigne($ligne4, 'Châtelet');
        $chatelet14 = $this->createDesserteSurLigne($ligne14, 'Châtelet');

        $clignancourt = $this->createDirection($ligne4, $this->createDesserteSurLigne($ligne4, 'Porte de Clignancourt'));
        $bagneux = $this->createDirection($ligne4, $this->createDesserteSurLigne($ligne4, 'Bagneux'));
        $stDenis = $this->createDirection($ligne14, $this->createDesserteSurLigne($ligne14, 'Saint-Denis Pleyel'));
        $orly = $this->createDirection($ligne14, $this->createDesserteSurLigne($ligne14, 'Aéroport d\'Orly'));

        $this->manager->flush();

        $premiere = new Correspondance();
        $premiere->setDesserteA($chatelet4);
        $premiere->setDesserteB($chatelet14);
        $premiere->setDirectionA($clignancourt);
        $premiere->setDirectionB($stDenis);
        $premiere->setDistance(180);
        $this->manager->persist($premiere);

        $seconde = new Correspondance();
        $seconde->setDesserteA($chatelet4);
        $seconde->setDesserteB($chatelet14);
        $seconde->setDirectionA($bagneux);
        $seconde->setDirectionB($orly);
        $seconde->setDistance(250);
        $this->manager->persist($seconde);

        $this->manager->flush();

        self::assertSame(2, $this->correspondanceRepository->count([]));
    }

    public function testRefuseUnDoublonExactDeCorrespondanceGenerale(): void
    {
        $desserteA = $this->createDesserte('Châtelet');
        $desserteB = $this->createDesserte('Les Halles');

        $existante = new Correspondance();
        $existante->setDesserteA($desserteA);
        $existante->setDesserteB($desserteB);
        $this->manager->persist($existante);
        $this->manager->flush();

        $crawler = $this->client->request('GET', sprintf('%snew', $this->path));

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['correspondance[desserteA]'] = (string) $desserteA->getId();
        $form['correspondance[desserteB]'] = (string) $desserteB->getId();
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->correspondanceRepository->count([]));
    }

    public function testShow(): void
    {
        $desserteA = $this->createDesserte('Châtelet');
        $desserteB = $this->createDesserte('Les Halles');

        $fixture = new Correspondance();
        $fixture->setDesserteA($desserteA);
        $fixture->setDesserteB($desserteB);
        $fixture->setDistance(250);

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Correspondance');
    }

    public function testEdit(): void
    {
        $desserteA = $this->createDesserte('Châtelet');
        $desserteB = $this->createDesserte('Les Halles');

        $fixture = new Correspondance();
        $fixture->setDesserteA($desserteA);
        $fixture->setDesserteB($desserteB);
        $fixture->setDistance(250);

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'correspondance[distance]' => '300',
        ]);

        self::assertResponseRedirects('/correspondance');

        $updated = $this->correspondanceRepository->find($fixture->getId());

        self::assertSame(300, $updated->getDistance());
    }

    public function testRemove(): void
    {
        $desserteA = $this->createDesserte('Châtelet');
        $desserteB = $this->createDesserte('Les Halles');

        $fixture = new Correspondance();
        $fixture->setDesserteA($desserteA);
        $fixture->setDesserteB($desserteB);

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/correspondance');
        self::assertSame(0, $this->correspondanceRepository->count([]));
    }
}
