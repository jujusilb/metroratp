<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->resetDatabase($this->manager);
    }

    public function testConnexionAvecBonsIdentifiants(): void
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $utilisateur = new Utilisateur();
        $utilisateur->setUsername('jean');
        $utilisateur->setEmail('jean@example.test');
        $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, 'bon-mot-de-passe'));
        $this->manager->persist($utilisateur);
        $this->manager->flush();

        $this->client->request('GET', '/login');
        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Se connecter', [
            '_username' => 'jean',
            '_password' => 'bon-mot-de-passe',
        ]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        // "/" redirige a son tour vers "/trajet" pour un utilisateur connecte.
        self::assertResponseRedirects('/trajet');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'jean');
    }

    public function testConnexionAvecMauvaisMotDePasseEchoue(): void
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $utilisateur = new Utilisateur();
        $utilisateur->setUsername('jean');
        $utilisateur->setEmail('jean@example.test');
        $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, 'bon-mot-de-passe'));
        $this->manager->persist($utilisateur);
        $this->manager->flush();

        $this->client->request('GET', '/login');

        $this->client->submitForm('Se connecter', [
            '_username' => 'jean',
            '_password' => 'mauvais-mot-de-passe',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }
}
