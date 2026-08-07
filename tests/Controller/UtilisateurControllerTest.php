<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UtilisateurControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Utilisateur> $utilisateurRepository */
    private EntityRepository $utilisateurRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private string $path = '/utilisateur/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->utilisateurRepository = $this->manager->getRepository(Utilisateur::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->resetDatabase($this->manager);
    }

    private function createAdmin(): Utilisateur
    {
        return $this->createUtilisateurAvecRoles('admin', ['ROLE_ADMIN']);
    }

    /**
     * @param list<string> $roles
     */
    private function createUtilisateurAvecRoles(string $username, array $roles): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setUsername($username);
        $utilisateur->setEmail($username.'@example.test');
        $utilisateur->setRoles($roles);
        $utilisateur->setPassword($this->passwordHasher->hashPassword($utilisateur, 'mot-de-passe'));

        $this->manager->persist($utilisateur);
        $this->manager->flush();

        return $utilisateur;
    }

    public function testAccesRefuseSansConnexion(): void
    {
        $this->client->request('GET', rtrim($this->path, '/'));

        self::assertResponseRedirects('/login');
    }

    public function testIndex(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);

        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Utilisateurs');
    }

    public function testNewHacheLeMotDePasse(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);

        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Enregistrer', [
            'utilisateur[username]' => 'testuser',
            'utilisateur[email]' => 'testuser@example.test',
            'utilisateur[plainPassword]' => 'mot-de-passe-test',
        ]);

        self::assertResponseRedirects('/utilisateur');

        $utilisateur = $this->utilisateurRepository->findOneBy(['username' => 'testuser']);
        self::assertNotNull($utilisateur);
        self::assertNotSame('mot-de-passe-test', $utilisateur->getPassword());
        self::assertTrue($this->passwordHasher->isPasswordValid($utilisateur, 'mot-de-passe-test'));
    }

    public function testEditSansMotDePasseConserveLAncien(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);

        $fixture = new Utilisateur();
        $fixture->setUsername('modifiable');
        $fixture->setEmail('modifiable@example.test');
        $fixture->setPassword($this->passwordHasher->hashPassword($fixture, 'ancien-mot-de-passe'));
        $this->manager->persist($fixture);
        $this->manager->flush();
        $ancienHash = $fixture->getPassword();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Mettre à jour', [
            'utilisateur[username]' => 'modifiable',
            'utilisateur[email]' => 'modifiable@example.test',
            'utilisateur[ville]' => 'Paris',
        ]);

        self::assertResponseRedirects('/utilisateur');

        $modifie = $this->utilisateurRepository->findOneBy(['username' => 'modifiable']);
        self::assertSame($ancienHash, $modifie->getPassword());
        self::assertSame('Paris', $modifie->getVille());
    }

    public function testRemove(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);

        $fixture = new Utilisateur();
        $fixture->setUsername('a-supprimer');
        $fixture->setEmail('a-supprimer@example.test');
        $fixture->setPassword($this->passwordHasher->hashPassword($fixture, 'peu-importe'));
        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/utilisateur');
        self::assertSame(1, $this->utilisateurRepository->count([])); // il reste l'admin
    }

    public function testUnAdminNePeutPasRetrograderUnAutreAdmin(): void
    {
        $admin = $this->createUtilisateurAvecRoles('admin1', ['ROLE_ADMIN']);
        $autreAdmin = $this->createUtilisateurAvecRoles('admin2', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);

        // Le champ estAdmin est desactive dans le HTML : un navigateur ne l'enverrait pas.
        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $autreAdmin->getId()));
        $this->client->submitForm('Mettre à jour', [
            'utilisateur[username]' => 'admin2',
            'utilisateur[email]' => 'admin2@example.test',
        ]);

        self::assertResponseRedirects('/utilisateur');

        $modifie = $this->utilisateurRepository->findOneBy(['username' => 'admin2']);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $modifie->getRoles());
    }

    public function testUnSuperadminPeutRetrograderUnAdmin(): void
    {
        $superadmin = $this->createUtilisateurAvecRoles('superadmin', ['ROLE_SUPERADMIN']);
        $admin = $this->createUtilisateurAvecRoles('admin', ['ROLE_ADMIN']);
        $this->client->loginUser($superadmin);

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $admin->getId()));
        $this->client->submitForm('Mettre à jour', [
            'utilisateur[username]' => 'admin',
            'utilisateur[email]' => 'admin@example.test',
            'utilisateur[estAdmin]' => false,
        ]);

        self::assertResponseRedirects('/utilisateur');

        $modifie = $this->utilisateurRepository->findOneBy(['username' => 'admin']);
        self::assertSame(['ROLE_USER'], $modifie->getRoles());
    }

    public function testUnAdminPeutPromouvoirUnUtilisateurSimple(): void
    {
        $admin = $this->createUtilisateurAvecRoles('admin', ['ROLE_ADMIN']);
        $simple = $this->createUtilisateurAvecRoles('simple', []);
        $this->client->loginUser($admin);

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $simple->getId()));
        $this->client->submitForm('Mettre à jour', [
            'utilisateur[username]' => 'simple',
            'utilisateur[email]' => 'simple@example.test',
            'utilisateur[estAdmin]' => true,
        ]);

        self::assertResponseRedirects('/utilisateur');

        $modifie = $this->utilisateurRepository->findOneBy(['username' => 'simple']);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $modifie->getRoles());
    }

    public function testUnAdminNePeutPasSupprimerUnAutreAdmin(): void
    {
        $admin = $this->createUtilisateurAvecRoles('admin1', ['ROLE_ADMIN']);
        $autreAdmin = $this->createUtilisateurAvecRoles('admin2', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);

        // Le formulaire de suppression n'est meme pas affiche pour une cible admin ; on
        // verifie ici que le controleur refuse aussi la requete si elle est forgee.
        $this->client->request('GET', sprintf('%s%s', $this->path, $autreAdmin->getId()));
        self::assertSelectorTextContains('body', 'Seul un super-administrateur peut supprimer un compte administrateur');

        $this->client->request('POST', sprintf('%s%s', $this->path, $autreAdmin->getId()));

        self::assertResponseRedirects('/utilisateur');
        self::assertNotNull($this->utilisateurRepository->find($autreAdmin->getId()));
    }
}
