<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class InscriptionControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Utilisateur> $utilisateurRepository */
    private EntityRepository $utilisateurRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->utilisateurRepository = $this->manager->getRepository(Utilisateur::class);
        $this->resetDatabase($this->manager);
    }

    public function testFormulaireAccessibleSansConnexion(): void
    {
        $this->client->request('GET', '/inscription');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('body', 'Créer un compte');
    }

    public function testInscriptionCreeUnCompteSimpleEtConnecteAutomatiquement(): void
    {
        $this->client->request('GET', '/inscription');

        $this->client->submitForm('Créer mon compte', [
            'utilisateur[username]' => 'nouveau',
            'utilisateur[email]' => 'nouveau@example.test',
            'utilisateur[plainPassword]' => 'mot-de-passe-solide',
        ]);

        self::assertResponseRedirects('/trajet');

        $utilisateur = $this->utilisateurRepository->findOneBy(['username' => 'nouveau']);
        self::assertNotNull($utilisateur);
        self::assertSame(['ROLE_USER'], $utilisateur->getRoles());

        // Deja connecte automatiquement apres inscription : une page reservee aux connectes
        // doit etre accessible sans nouveau login.
        $this->client->request('GET', '/mon-profil');
        self::assertResponseStatusCodeSame(200);
    }

    public function testInscriptionRefuseUnMotDePasseTropCourt(): void
    {
        $this->client->request('GET', '/inscription');

        $this->client->submitForm('Créer mon compte', [
            'utilisateur[username]' => 'nouveau',
            'utilisateur[email]' => 'nouveau@example.test',
            'utilisateur[plainPassword]' => 'court',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->utilisateurRepository->findOneBy(['username' => 'nouveau']));
    }

    public function testUnUtilisateurDejaConnecteEstRedirige(): void
    {
        $passwordHasher = static::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
        $utilisateur = new Utilisateur();
        $utilisateur->setUsername('deja_connecte');
        $utilisateur->setEmail('deja_connecte@example.test');
        $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, 'peu-importe'));
        $this->manager->persist($utilisateur);
        $this->manager->flush();

        $this->client->loginUser($utilisateur);
        $this->client->request('GET', '/inscription');

        self::assertResponseRedirects('/trajet');
    }
}
