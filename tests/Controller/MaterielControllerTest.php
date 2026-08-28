<?php

namespace App\Tests\Controller;

use App\Entity\Ligne;
use App\Entity\Materiel;
use App\Entity\TypeMateriel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class MaterielControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Materiel> $materielRepository */
    private EntityRepository $materielRepository;
    private string $path = '/materiel/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->materielRepository = $this->manager->getRepository(Materiel::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    private function createTypeMateriel(): TypeMateriel
    {
        $typeMateriel = new TypeMateriel();
        $typeMateriel->setLabel('MF 01');

        $this->manager->persist($typeMateriel);
        $this->manager->flush();

        return $typeMateriel;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Matériel');
    }

    public function testNew(): void
    {
        $typeMateriel = $this->createTypeMateriel();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'materiel[label]' => 'MF 01',
            'materiel[anneeProduction]' => '2001',
            'materiel[typeMateriel]' => $typeMateriel->getId(),
        ]);

        self::assertResponseRedirects('/materiel');

        self::assertSame(1, $this->materielRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Materiel();
        $fixture->setLabel('My Title');
        $fixture->setAnneeProduction('2001');
        $fixture->setTypeMateriel($this->createTypeMateriel());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Materiel');
    }

    public function testEdit(): void
    {
        $fixture = new Materiel();
        $fixture->setLabel('Value');
        $fixture->setAnneeProduction('2001');
        $fixture->setTypeMateriel($this->createTypeMateriel());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'materiel[label]' => 'Something New',
        ]);

        self::assertResponseRedirects('/materiel');

        $fixture = $this->materielRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getLabel());
    }

    /**
     * Le CollectionType imbrique (voir MaterielType) ne rend aucun champ quand la collection est
     * vide - meme raisonnement que DepotControllerTest::testEditAjouteUnGestionnaireDate().
     */
    public function testEditAjouteUneLigneDatee(): void
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');
        $this->manager->persist($ligne);

        $fixture = new Materiel();
        $fixture->setLabel('Z 6100');
        $fixture->setAnneeProduction('1970');
        $fixture->setTypeMateriel($this->createTypeMateriel());
        $this->manager->persist($fixture);
        $this->manager->flush();

        $crawler = $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));
        $token = $crawler->filter('input[name="materiel[_token]"]')->attr('value');

        $this->client->request('POST', sprintf('%s%s/edit', $this->path, $fixture->getId()), [
            'materiel' => [
                'label' => $fixture->getLabel(),
                'anneeProduction' => $fixture->getAnneeProduction(),
                'typeMateriel' => $fixture->getTypeMateriel()->getId(),
                'materielLignes' => [
                    0 => ['ligne' => $ligne->getId(), 'arrivee' => '1970-01-01'],
                ],
                '_token' => $token,
            ],
        ]);

        self::assertResponseRedirects('/materiel');

        $this->manager->clear();
        $updated = $this->manager->getRepository(Materiel::class)->find($fixture->getId());

        self::assertCount(1, $updated->getMaterielLignes());
        self::assertSame('1', $updated->getMaterielLignes()->first()->getLigne()->getLabel());
    }

    public function testRemove(): void
    {
        $fixture = new Materiel();
        $fixture->setLabel('Value');
        $fixture->setAnneeProduction('2001');
        $fixture->setTypeMateriel($this->createTypeMateriel());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/materiel');
        self::assertSame(0, $this->materielRepository->count([]));
    }
}
