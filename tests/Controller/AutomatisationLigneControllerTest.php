<?php

namespace App\Tests\Controller;

use App\Entity\Automatisation;
use App\Entity\AutomatisationLigne;
use App\Entity\Ligne;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AutomatisationLigneControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<AutomatisationLigne> $automatisationLigneRepository */
    private EntityRepository $automatisationLigneRepository;
    private string $path = '/automatisation/ligne/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->automatisationLigneRepository = $this->manager->getRepository(AutomatisationLigne::class);
        $this->resetDatabase($this->manager);
        $this->connecterEnAdmin($this->client, $this->manager);
    }

    private function createAutomatisation(): Automatisation
    {
        $automatisation = new Automatisation();
        $automatisation->setLabel('Total');
        $this->manager->persist($automatisation);
        $this->manager->flush();

        return $automatisation;
    }

    private function createLigne(): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel('14');

        $this->manager->persist($ligne);
        $this->manager->flush();

        return $ligne;
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Automatisation-Ligne');
    }

    public function testNew(): void
    {
        $automatisation = $this->createAutomatisation();
        $ligne = $this->createLigne();

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'automatisation_ligne[dateDeMiseEnPlace]' => '1998-10-15',
            'automatisation_ligne[automatisation]' => $automatisation->getId(),
            'automatisation_ligne[ligne]' => $ligne->getId(),
        ]);

        self::assertResponseRedirects('/automatisation/ligne');

        self::assertSame(1, $this->automatisationLigneRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new AutomatisationLigne();
        $fixture->setDateDeMiseEnPlace(new \DateTime('1998-10-15'));
        $fixture->setAutomatisation($this->createAutomatisation());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('AutomatisationLigne');
    }

    public function testEdit(): void
    {
        $fixture = new AutomatisationLigne();
        $fixture->setDateDeMiseEnPlace(new \DateTime('1998-10-15'));
        $fixture->setAutomatisation($this->createAutomatisation());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'automatisation_ligne[dateDeMiseEnPlace]' => '2011-04-01',
        ]);

        self::assertResponseRedirects('/automatisation/ligne');

        $fixture = $this->automatisationLigneRepository->findAll();

        self::assertSame('2011-04-01', $fixture[0]->getDateDeMiseEnPlace()->format('Y-m-d'));
    }

    public function testRemove(): void
    {
        $fixture = new AutomatisationLigne();
        $fixture->setDateDeMiseEnPlace(new \DateTime('1998-10-15'));
        $fixture->setAutomatisation($this->createAutomatisation());
        $fixture->setLigne($this->createLigne());

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/automatisation/ligne');
        self::assertSame(0, $this->automatisationLigneRepository->count([]));
    }
}
