<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Service\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class VerificationEmailControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    /** @var EntityRepository<Utilisateur> $utilisateurRepository */
    private EntityRepository $utilisateurRepository;
    private Utilisateur $utilisateur;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->utilisateurRepository = $this->manager->getRepository(Utilisateur::class);
        $this->resetDatabase($this->manager);

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->utilisateur = new Utilisateur();
        $this->utilisateur->setUsername('a_verifier');
        $this->utilisateur->setEmail('a_verifier@example.test');
        $this->utilisateur->setPassword($passwordHasher->hashPassword($this->utilisateur, 'peu-importe'));
        $this->manager->persist($this->utilisateur);
        $this->manager->flush();
    }

    private function genererLienSigne(): string
    {
        $verifier = static::getContainer()->get(EmailVerifier::class);
        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@example.test'))
            ->to((string) $this->utilisateur->getEmail())
            ->subject('Test')
            ->htmlTemplate('inscription/confirmation_email.html.twig');
        $verifier->envoyerEmailConfirmation('app_verifier_email', $this->utilisateur, $email);

        $message = self::getMailerMessage(0);
        preg_match('#href="([^"]+)"#', $message->getHtmlBody(), $matches);

        // Le "&" est rendu "&amp;" dans l'attribut href par l'echappement Twig par defaut (un
        // vrai navigateur/client mail le decode automatiquement avant de suivre le lien).
        return html_entity_decode($matches[1]);
    }

    public function testCliquerLeLienValideMarqueLeCompteVerifie(): void
    {
        self::assertFalse($this->utilisateur->isVerified());

        $lien = $this->genererLienSigne();
        $this->client->request('GET', $lien);

        self::assertResponseRedirects('/trajet');

        $this->manager->clear();
        $utilisateur = $this->utilisateurRepository->find($this->utilisateur->getId());
        self::assertTrue($utilisateur->isVerified());
    }

    public function testSignatureInvalideNeVerifiePasEtNeCasseRien(): void
    {
        $this->client->request('GET', '/verifier/email?expires=9999999999&id='.$this->utilisateur->getId().'&signature=invalide&token=invalide');

        self::assertResponseRedirects('/trajet');

        $this->manager->clear();
        $utilisateur = $this->utilisateurRepository->find($this->utilisateur->getId());
        self::assertFalse($utilisateur->isVerified());
    }

    public function testSansIdRedirigeSansErreur(): void
    {
        $this->client->request('GET', '/verifier/email');

        self::assertResponseRedirects('/trajet');
    }
}
