<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

            $security->login($utilisateur);

            return $this->redirectToRoute('app_trajet_index');
        }

        return $this->render('inscription/index.html.twig', [
            'form' => $form,
        ]);
    }
}
