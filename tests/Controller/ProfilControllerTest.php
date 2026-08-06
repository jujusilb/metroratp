<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProfilControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->resetDatabase($this->manager);
    }

    private function createUtilisateur(): Utilisateur
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $utilisateur = new Utilisateur();
        $utilisateur->setUsername('simple_user');
        $utilisateur->setEmail('simple_user@example.test');
        $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, 'ancien-mot-de-passe'));
        $this->manager->persist($utilisateur);
        $this->manager->flush();

        return $utilisateur;
    }

    public function testUnUtilisateurNonAdminPeutVoirSonPropreProfil(): void
    {
        $utilisateur = $this->createUtilisateur();
        $this->client->loginUser($utilisateur);

        $this->client->request('GET', '/mon-profil');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('body', 'simple_user');
    }

    public function testUnUtilisateurNonAdminPeutModifierSonPropreProfilSansToucherAuxRoles(): void
    {
        $utilisateur = $this->createUtilisateur();
        $ancienHash = $utilisateur->getPassword();
        $this->client->loginUser($utilisateur);

        $this->client->request('GET', '/mon-profil/edit');
        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'utilisateur[username]' => 'simple_user',
            'utilisateur[email]' => 'simple_user@example.test',
            'utilisateur[ville]' => 'Paris',
        ]);

        self::assertResponseRedirects('/mon-profil');

        $utilisateurRepository = $this->manager->getRepository(Utilisateur::class);
        $modifie = $utilisateurRepository->findOneBy(['username' => 'simple_user']);
        self::assertSame('Paris', $modifie->getVille());
        self::assertSame($ancienHash, $modifie->getPassword());
        self::assertSame(['ROLE_USER'], $modifie->getRoles());
    }

    public function testUnUtilisateurNonAdminNePeutPasAccederAuCrudUtilisateurDesAutres(): void
    {
        $utilisateur = $this->createUtilisateur();
        $autre = new Utilisateur();
        $autre->setUsername('autre_user');
        $autre->setEmail('autre_user@example.test');
        $autre->setPassword('peu-importe-hash');
        $this->manager->persist($autre);
        $this->manager->flush();

        $this->client->loginUser($utilisateur);

        $this->client->request('GET', sprintf('/utilisateur/%d/edit', $autre->getId()));
        self::assertResponseStatusCodeSame(403);
    }
}
