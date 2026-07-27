<?php

namespace App\Tests\Controller;

use App\Entity\Ligne;
use App\Entity\Materiel;
use App\Entity\MaterielLigne;
use App\Entity\TypeMateriel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class MaterielLigneControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<MaterielLigne> $materielLigneRepository */
    private EntityRepository $materielLigneRepository;
    private string $path = '/materiel/ligne/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->materielLigneRepository = $this->manager->getRepository(MaterielLigne::class);
        $this->resetDatabase($this->manager);
    }

    private function createMateriel(): Materiel
    {
        $typeMateriel = new TypeMateriel();
        $typeMateriel->setLabel('MF 01');
        $this->manager->persist($typeMateriel);

        $materiel = new Materiel();
        $materiel->setLabel('MF 01');
        $materiel->setAnneeProduction('2001');
        $materiel->setTypeMateriel($typeMateriel);
        $this->manager->persist($materiel);

        $this->manager->flush();

        return $materiel;
    }

    private function createLigne(): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel('1');

        $this->manager->persist($ligne);
        $this->manager->flush();

        return $ligne;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Matériel-Ligne');
    }

    public function testNew(): void
    {
        $materiel = $this->createMateriel();
        $ligne = $this->createLigne();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'materiel_ligne[arrivee]' => '2001-01-01',
            'materiel_ligne[materiel]' => $materiel->getId(),
            'materiel_ligne[ligne]' => $ligne->getId(),
        ]);

        self::assertResponseRedirects('/materiel/ligne');

        self::assertSame(1, $this->materielLigneRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new MaterielLigne();
        $fixture->setArrivee(new \DateTime('2001-01-01'));
        $fixture->setMateriel($this->createMateriel());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('MaterielLigne');
    }

    public function testEdit(): void
    {
        $fixture = new MaterielLigne();
        $fixture->setArrivee(new \DateTime('2001-01-01'));
        $fixture->setMateriel($this->createMateriel());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'materiel_ligne[arrivee]' => '2005-06-15',
        ]);

        self::assertResponseRedirects('/materiel/ligne');

        $fixture = $this->materielLigneRepository->findAll();

        self::assertSame('2005-06-15', $fixture[0]->getArrivee()->format('Y-m-d'));
    }

    public function testRemove(): void
    {
        $fixture = new MaterielLigne();
        $fixture->setArrivee(new \DateTime('2001-01-01'));
        $fixture->setMateriel($this->createMateriel());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/materiel/ligne');
        self::assertSame(0, $this->materielLigneRepository->count([]));
    }
}
