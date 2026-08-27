<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Service\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class InscriptionController extends AbstractController
{
    #[Route('/inscription', name: 'app_inscription', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        EmailVerifier $emailVerifier,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_trajet_index');
        }

        $utilisateur = new Utilisateur();
        // Formulaire sans champ "estAdmin" : un compte cree via l'inscription publique est
        // toujours un simple utilisateur (ROLE_USER), jamais admin ou superadmin.
        $form = $this->createForm(UtilisateurType::class, $utilisateur, [
            'montrer_admin' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, $form->get('plainPassword')->getData()));
            $utilisateur->setRoles([]);

            $entityManager->persist($utilisateur);
            $entityManager->flush();

            $emailVerifier->envoyerEmailConfirmation('app_verifier_email', $utilisateur, (new TemplatedEmail())
                ->from(new Address('no-reply@julien-silberstein.fr', 'metroratp'))
                ->to((string) $utilisateur->getEmail())
                ->subject('Confirmez votre adresse email')
                ->htmlTemplate('inscription/confirmation_email.html.twig'));

            // Connexion immediate malgre l'email non encore verifie : le compte est deja
            // utilisable, la verification ne fait que confirmer l'adresse email plutot que
            // bloquer l'usage du site le temps du clic.
            $security->login($utilisateur);

            $this->addFlash('success', 'Compte cree ! Un email de confirmation vient de vous etre envoye, pensez a verifier votre adresse.');

            return $this->redirectToRoute('app_trajet_index');
        }

        return $this->render('inscription/index.html.twig', [
            'form' => $form,
        ]);
    }
}
