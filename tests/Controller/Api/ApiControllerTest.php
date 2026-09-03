<?php

namespace App\Tests\Controller\Api;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use App\Entity\Gestionnaire;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\TypeTransport;
use App\Tests\Controller\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Verifie que les 6 endpoints /api/* (lecture seule, voir src/Controller/Api/) repondent en JSON
 * avec la forme attendue, ET qu'ils sont accessibles SANS connexion (PUBLIC_ACCESS dans
 * security.yaml - un client Kotlin n'a pas de session navigateur), contrairement au reste du site.
 */
final class ApiControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->resetDatabase($this->manager);
    }

    public function testApiTypeTransportIndexSansConnexion(): void
    {
        $typeTransport = new TypeTransport();
        $typeTransport->setLabel('Métro');
        $this->manager->persist($typeTransport);
        $this->manager->flush();

        $this->client->request('GET', '/api/types-transport');

        self::assertResponseIsSuccessful();
        $donnees = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $donnees);
        self::assertSame('Métro', $donnees[0]['label']);
    }

    public function testApiGestionnaireIndex(): void
    {
        $gestionnaire = new Gestionnaire();
        $gestionnaire->setLabel('RATP');
        $this->manager->persist($gestionnaire);
        $this->manager->flush();

        $this->client->request('GET', '/api/gestionnaires');

        self::assertResponseIsSuccessful();
        $donnees = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('RATP', $donnees[0]['label']);
    }

    public function testApiLigneIndex(): void
    {
        $typeTransport = new TypeTransport();
        $typeTransport->setLabel('Métro');
        $this->manager->persist($typeTransport);

        $gestionnaire = new Gestionnaire();
        $gestionnaire->setLabel('RATP');
        $this->manager->persist($gestionnaire);

        $ligne = new Ligne();
        $ligne->setLabel('1');
        $ligne->setTypeTransport($typeTransport);
        $ligne->setGestionnaire($gestionnaire);
        $this->manager->persist($ligne);
        $this->manager->flush();

        $this->client->request('GET', '/api/lignes');

        self::assertResponseIsSuccessful();
        $donnees = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('1', $donnees[0]['label']);
        self::assertSame($typeTransport->getId(), $donnees[0]['typeTransportId']);
        self::assertSame($gestionnaire->getId(), $donnees[0]['gestionnaireId']);
    }

    public function testApiStationIndex(): void
    {
        $station = new Station();
        $station->setLabel('Châtelet');
        $station->setLatitude(48.858);
        $station->setLongitude(2.347);
        $this->manager->persist($station);
        $this->manager->flush();

        $this->client->request('GET', '/api/stations');

        self::assertResponseIsSuccessful();
        $donnees = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Châtelet', $donnees[0]['label']);
        self::assertSame(48.858, $donnees[0]['latitude']);
    }

    public function testApiDesserteIndex(): void
    {
        $typeTransport = new TypeTransport();
        $typeTransport->setLabel('Métro');
        $this->manager->persist($typeTransport);

        $ligne = new Ligne();
        $ligne->setLabel('1');
        $ligne->setTypeTransport($typeTransport);
        $this->manager->persist($ligne);

        $station = new Station();
        $station->setLabel('Châtelet');
        $this->manager->persist($station);

        $desserte = new Desserte();
        $desserte->setLigne($ligne);
        $desserte->setStation($station);
        $this->manager->persist($desserte);
        $this->manager->flush();

        $this->client->request('GET', '/api/dessertes');

        self::assertResponseIsSuccessful();
        $donnees = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($station->getId(), $donnees[0]['stationId']);
        self::assertSame($ligne->getId(), $donnees[0]['ligneId']);
    }

    public function testApiCorrespondanceIndex(): void
    {
        $typeTransport = new TypeTransport();
        $typeTransport->setLabel('Métro');
        $this->manager->persist($typeTransport);

        $ligne = new Ligne();
        $ligne->setLabel('1');
        $ligne->setTypeTransport($typeTransport);
        $this->manager->persist($ligne);

        $stationA = new Station();
        $stationA->setLabel('Châtelet');
        $this->manager->persist($stationA);

        $stationB = new Station();
        $stationB->setLabel('Les Halles');
        $this->manager->persist($stationB);

        $desserteA = new Desserte();
        $desserteA->setLigne($ligne);
        $desserteA->setStation($stationA);
        $this->manager->persist($desserteA);

        $desserteB = new Desserte();
        $desserteB->setLigne($ligne);
        $desserteB->setStation($stationB);
        $this->manager->persist($desserteB);

        $correspondance = new Correspondance();
        $correspondance->setDesserteA($desserteA);
        $correspondance->setDesserteB($desserteB);
        $correspondance->setDistance(120);
        $this->manager->persist($correspondance);
        $this->manager->flush();

        $this->client->request('GET', '/api/correspondances');

        self::assertResponseIsSuccessful();
        $donnees = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(120, $donnees[0]['distance']);
        self::assertSame($desserteA->getId(), $donnees[0]['desserteAId']);
        self::assertSame($desserteB->getId(), $donnees[0]['desserteBId']);
    }
}
