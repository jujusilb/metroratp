<?php

namespace App\Tests\Controller;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\StyleEcriture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class StyleEcritureControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<StyleEcriture> $styleEcritureRepository */
    private EntityRepository $styleEcritureRepository;
    private string $path = '/style/ecriture/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->styleEcritureRepository = $this->manager->getRepository(StyleEcriture::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Styles');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'style_ecriture[label]' => 'Testing',
        ]);

        self::assertResponseRedirects('/style/ecriture');

        self::assertSame(1, $this->styleEcritureRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new StyleEcriture();
        $fixture->setLabel('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('StyleEcriture');
    }

    public function testEdit(): void
    {
        $fixture = new StyleEcriture();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'style_ecriture[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/style/ecriture');

        $fixture = $this->styleEcritureRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    /**
     * Le CollectionType imbrique (voir StyleEcritureType) ne rend aucun champ quand la collection
     * est vide - meme raisonnement que MaterielControllerTest::testEditAjouteUneLigneDatee().
     */
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

        $fixture = new StyleEcriture();
        $fixture->setLabel('Parisine');
        $this->manager->persist($fixture);
        $this->manager->flush();

        $crawler = $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));
        $token = $crawler->filter('input[name="style_ecriture[_token]"]')->attr('value');

        $this->client->request('POST', sprintf('%s%s/edit', $this->path, $fixture->getId()), [
            'style_ecriture' => [
                'label' => $fixture->getLabel(),
                'dessertes' => [
                    0 => $desserteA->getId(),
                    1 => $desserteB->getId(),
                ],
                '_token' => $token,
            ],
        ]);

        self::assertResponseRedirects('/style/ecriture');

        $this->manager->clear();
        $updated = $this->styleEcritureRepository->find($fixture->getId());

        self::assertCount(2, $updated->getDessertes());
    }

    public function testRemove(): void
    {
        $fixture = new StyleEcriture();
        $fixture->setLabel('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/style/ecriture');
        self::assertSame(0, $this->styleEcritureRepository->count([]));
    }
}
