<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-profil')]
#[IsGranted('ROLE_USER')]
final class ProfilController extends AbstractController
{
    #[Route(name: 'app_mon_profil', methods: ['GET'])]
    public function show(): Response
    {
        return $this->render('profil/show.html.twig', [
            'utilisateur' => $this->utilisateurCourant(),
        ]);
    }

    #[Route('/edit', name: 'app_mon_profil_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $utilisateur = $this->utilisateurCourant();

        $form = $this->createForm(UtilisateurType::class, $utilisateur, [
            'password_requis' => false,
            'montrer_admin' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, $plainPassword));
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_mon_profil', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('profil/edit.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form,
        ]);
    }

    private function utilisateurCourant(): Utilisateur
    {
        $utilisateur = $this->getUser();

        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $utilisateur;
    }
}
