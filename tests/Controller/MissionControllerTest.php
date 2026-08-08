<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Direction;
use App\Entity\Ligne;
use App\Entity\Mission;
use App\Entity\Service;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class MissionControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Mission> $missionRepository */
    private EntityRepository $missionRepository;
    private string $path = '/mission/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->missionRepository = $this->manager->getRepository(Mission::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    private function createStation(string $label): Station
    {
        $station = new Station();
        $station->setLabel($label);
        $this->manager->persist($station);

        return $station;
    }

    private function createLigne(): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);

        return $ligne;
    }

    private function createDesserte(Ligne $ligne, Station $station): Desserte
    {
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

    private function createTypeDesserte(string $label): TypeDesserte
    {
        $typeDesserte = new TypeDesserte();
        $typeDesserte->setLabel($label);
        $this->manager->persist($typeDesserte);

        return $typeDesserte;
    }

    private function createTronconDesserte(Troncon $troncon, Desserte $desserte, TypeDesserte $typeDesserte): TronconDesserte
    {
        $tronconDesserte = new TronconDesserte();
        $tronconDesserte->setTroncon($troncon);
        $tronconDesserte->setDesserte($desserte);
        $tronconDesserte->setTypeDesserte($typeDesserte);
        $this->manager->persist($tronconDesserte);

        return $tronconDesserte;
    }

    private function createService(string $label = 'Unique'): Service
    {
        $service = new Service();
        $service->setLabel($label);
        $this->manager->persist($service);

        return $service;
    }

    /**
     * Construit une ligne a 3 stations (A -> B -> C) avec 2 troncons et 2 missions,
     * pour tester le parcours complet (choix_ligne -> choix_direction -> choix_service -> trajet).
     *
     * @return array{ligne: Ligne, missions: Mission[], stations: Station[], direction: Direction, service: Service}
     */
    private function createLigneAvecTrajet(): array
    {
        $ligne = $this->createLigne();
        $stationA = $this->createStation('Châtelet');
        $stationB = $this->createStation('Bastille');
        $stationC = $this->createStation('Nation');

        $departType = $this->createTypeDesserte('Départ');
        $arriveeType = $this->createTypeDesserte('Arrivée');

        $dA = $this->createDesserte($ligne, $stationA);
        $dB = $this->createDesserte($ligne, $stationB);
        $dC = $this->createDesserte($ligne, $stationC);

        $t1 = new Troncon();
        $this->manager->persist($t1);

        $t2 = new Troncon();
        $this->manager->persist($t2);

        $td1Depart = $this->createTronconDesserte($t1, $dA, $departType);
        $this->createTronconDesserte($t1, $dB, $arriveeType);
        $td2Depart = $this->createTronconDesserte($t2, $dB, $departType);
        $this->createTronconDesserte($t2, $dC, $arriveeType);

        $service = $this->createService();
        $direction = $this->createDirection($ligne, $dC);

        $mission1 = new Mission();
        $mission1->setNumero(1);
        $mission1->setService($service);
        $mission1->setTronconDesserte($td1Depart);
        $mission1->setDirection($direction);
        $this->manager->persist($mission1);

        $mission2 = new Mission();
        $mission2->setNumero(2);
        $mission2->setService($service);
        $mission2->setTronconDesserte($td2Depart);
        $mission2->setDirection($direction);
        $this->manager->persist($mission2);

        $this->manager->flush();
        // Vide l'identity map : sans ca, les collections inverses (Troncon::tronconDessertes)
        // restent vides en memoire cote test, alors qu'une vraie requete HTTP repartirait toujours
        // d'un EntityManager frais et les rechargerait correctement depuis la base.
        $this->manager->clear();

        return [
            'ligne' => $ligne,
            'missions' => [$mission1, $mission2],
            'stations' => [$stationA, $stationB, $stationC],
            'direction' => $direction,
            'service' => $service,
        ];
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Missions');
    }

    public function testChoixDirection(): void
    {
        $data = $this->createLigneAvecTrajet();

        $this->client->request('GET', sprintf('/mission/ligne/%d', $data['ligne']->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('body', 'Nation');
    }

    public function testTrajetAffricheToutesLesStationsSansSauter(): void
    {
        $data = $this->createLigneAvecTrajet();
        $ligne = $data['ligne'];
        $direction = $data['direction'];
        $service = $data['service'];

        $crawler = $this->client->request(
            'GET',
            sprintf('/mission/ligne/%d/direction/%d/service/%d', $ligne->getId(), $direction->getId(), $service->getId())
        );

        self::assertResponseStatusCodeSame(200);

        // Regression : les 3 stations du trajet doivent toutes apparaitre, dans l'ordre,
        // sans qu'aucune ne soit "sautee" (bug historique lie a un numero de mission NULL).
        // On se limite a la liste elle-meme : l'en-tete affiche deja "Direction Nation" avant
        // la liste, ce qui fausserait l'ordre si on cherchait dans le body entier.
        $text = $crawler->filter('ul.parcours-list')->text();
        $posChatelet = strpos($text, 'Châtelet');
        $posBastille = strpos($text, 'Bastille');
        $posNation = strpos($text, 'Nation');

        self::assertNotFalse($posChatelet);
        self::assertNotFalse($posBastille);
        self::assertNotFalse($posNation);
        self::assertTrue($posChatelet < $posBastille);
        self::assertTrue($posBastille < $posNation);
    }

    public function testNew(): void
    {
        $data = $this->createLigneAvecTrajet();
        $tronconDesserte = $data['missions'][0]->getTronconDesserte();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'mission[numero]' => '99',
            'mission[service]' => $data['service']->getId(),
            'mission[tronconDesserte]' => $tronconDesserte->getId(),
            'mission[direction]' => $data['direction']->getId(),
        ]);

        self::assertResponseRedirects('/mission');

        self::assertSame(3, $this->missionRepository->count([]));
    }

    public function testShow(): void
    {
        $data = $this->createLigneAvecTrajet();
        $mission = $data['missions'][0];

        $this->client->request('GET', sprintf('%s%s', $this->path, $mission->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Mission');
    }

    public function testEdit(): void
    {
        $data = $this->createLigneAvecTrajet();
        $mission = $data['missions'][0];

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $mission->getId()));

        $this->client->submitForm('Mettre à jour', [
            'mission[numero]' => '42',
        ]);

        self::assertResponseRedirects('/mission');

        $updated = $this->missionRepository->find($mission->getId());

        self::assertSame(42, $updated->getNumero());
    }

    public function testRemove(): void
    {
        $data = $this->createLigneAvecTrajet();
        $mission = $data['missions'][0];
        $countBefore = $this->missionRepository->count([]);

        $this->client->request('GET', sprintf('%s%s', $this->path, $mission->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/mission');
        self::assertSame($countBefore - 1, $this->missionRepository->count([]));
    }
}
