<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/utilisateur')]
#[IsGranted('ROLE_ADMIN')]
final class UtilisateurController extends AbstractController
{
    #[Route(name: 'app_utilisateur_index', methods: ['GET'])]
    public function index(Request $request, UtilisateurRepository $utilisateurRepository, PaginatorInterface $paginator): Response
    {
        $lettre = $request->query->get('lettre');
        $recherche = $request->query->get('q');

        $qb = $utilisateurRepository->createQueryBuilder('u')->orderBy('u.username', 'ASC');
        $utilisateurRepository->appliquerFiltreAlphabetEtRecherche($qb, 'u.username', $lettre, $recherche);

        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
            'lettre' => $lettre,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/new', name: 'app_utilisateur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $utilisateur = new Utilisateur();
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, $form->get('plainPassword')->getData()));
            // Un nouveau compte n'est jamais admin/superadmin au depart : toujours modifiable.
            $utilisateur->setRoles($form->get('estAdmin')->getData() ? ['ROLE_ADMIN'] : []);

            $entityManager->persist($utilisateur);
            $entityManager->flush();

            return $this->redirectToRoute('app_utilisateur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('utilisateur/new.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_utilisateur_show', methods: ['GET'])]
    public function show(Utilisateur $utilisateur): Response
    {
        return $this->render('utilisateur/show.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_utilisateur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Utilisateur $utilisateur, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $peutModifierAdmin = $this->peutModifierRoleAdmin($utilisateur);

        $form = $this->createForm(UtilisateurType::class, $utilisateur, [
            'password_requis' => false,
            'admin_par_defaut' => \in_array('ROLE_ADMIN', $utilisateur->getRoles(), true),
            'admin_modifiable' => $peutModifierAdmin,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, $plainPassword));
            }
            // Champ desactive cote formulaire quand non modifiable, mais on l'ignore aussi
            // explicitement ici : un admin ne doit jamais pouvoir retrograder un autre admin
            // (ou un superadmin), meme en forgeant la requete.
            if ($peutModifierAdmin) {
                $utilisateur->setRoles($form->get('estAdmin')->getData() ? ['ROLE_ADMIN'] : []);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_utilisateur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('utilisateur/edit.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form,
        ]);
    }

    /**
     * Un simple admin peut promouvoir un utilisateur en admin, mais jamais retrograder un
     * admin (ou toucher a un superadmin). Seul un superadmin peut retirer ROLE_ADMIN.
     */
    private function peutModifierRoleAdmin(Utilisateur $cible): bool
    {
        if (\in_array('ROLE_SUPERADMIN', $cible->getRoles(), true)) {
            return false;
        }

        if (\in_array('ROLE_ADMIN', $cible->getRoles(), true)) {
            return $this->isGranted('ROLE_SUPERADMIN');
        }

        return true;
    }

    #[Route('/{id}', name: 'app_utilisateur_delete', methods: ['POST'])]
    public function delete(Request $request, Utilisateur $utilisateur, EntityManagerInterface $entityManager): Response
    {
        // Sinon un simple admin contournerait l'interdiction de retrograder un admin en le
        // supprimant purement et simplement.
        if ($this->peutModifierRoleAdmin($utilisateur)
            && $this->isCsrfTokenValid('delete'.$utilisateur->getId(), $request->getPayload()->getString('_token'))
        ) {
            $entityManager->remove($utilisateur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_utilisateur_index', [], Response::HTTP_SEE_OTHER);
    }
}
