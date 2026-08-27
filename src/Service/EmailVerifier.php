<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Genere et envoie le lien de verification a l'inscription, puis valide le clic. Signature
 * signee/expirable geree par le bundle (voir SymfonyCastsVerifyEmailBundle) - aucun token stocke
 * en base, contrairement a une implementation maison.
 */
class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function envoyerEmailConfirmation(string $routeVerification, Utilisateur $utilisateur, TemplatedEmail $email): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $routeVerification,
            (string) $utilisateur->getId(),
            $utilisateur->getEmail(),
            ['id' => $utilisateur->getId()],
        );

        $context = $email->getContext();
        $context['signedUrl'] = $signatureComponents->getSignedUrl();
        $context['expiresAtMessageKey'] = $signatureComponents->getExpirationMessageKey();
        $context['expiresAtMessageData'] = $signatureComponents->getExpirationMessageData();

        $email->context($context);

        $this->mailer->send($email);
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, Utilisateur $utilisateur): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            (string) $utilisateur->getId(),
            $utilisateur->getEmail(),
        );

        $utilisateur->setVerified(true);
        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();
    }
}
