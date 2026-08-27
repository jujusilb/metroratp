<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use App\Service\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class VerificationEmailController extends AbstractController
{
    #[Route('/verifier/email', name: 'app_verifier_email')]
    public function verifierEmail(Request $request, UtilisateurRepository $utilisateurRepository, EmailVerifier $emailVerifier): RedirectResponse
    {
        $id = $request->query->get('id');
        if (null === $id) {
            return $this->redirectToRoute('app_trajet_index');
        }

        $utilisateur = $utilisateurRepository->find($id);
        if (null === $utilisateur) {
            return $this->redirectToRoute('app_trajet_index');
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $utilisateur);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());

            return $this->redirectToRoute('app_trajet_index');
        }

        $this->addFlash('success', 'Adresse email confirmee, merci !');

        return $this->redirectToRoute('app_trajet_index');
    }
}
