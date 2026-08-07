<?php

namespace App\Command;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Cree un compte utilisateur en ligne de commande. Necessaire pour amorcer le tout premier
 * compte administrateur : /utilisateur est reserve a ROLE_ADMIN, donc personne ne peut creer
 * de compte via le site tant qu'aucun admin n'existe deja.
 */
#[AsCommand(name: 'app:creer-utilisateur', description: 'Cree un utilisateur (mot de passe hache), eventuellement administrateur')]
class CreerUtilisateurCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, "Nom d'utilisateur")
            ->addArgument('email', InputArgument::REQUIRED, 'Email')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe en clair (sera hache)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Attribue ROLE_ADMIN à ce compte')
            ->addOption('superadmin', null, InputOption::VALUE_NONE, 'Attribue ROLE_SUPERADMIN à ce compte (au-dessus de ROLE_ADMIN, non attribuable depuis le site)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $roles = [];
        $libelle = '';
        if ($input->getOption('superadmin')) {
            $roles = ['ROLE_SUPERADMIN'];
            $libelle = ' (super-administrateur)';
        } elseif ($input->getOption('admin')) {
            $roles = ['ROLE_ADMIN'];
            $libelle = ' (administrateur)';
        }

        $utilisateur = new Utilisateur();
        $utilisateur->setUsername($input->getArgument('username'));
        $utilisateur->setEmail($input->getArgument('email'));
        $utilisateur->setRoles($roles);
        $utilisateur->setPassword($this->passwordHasher->hashPassword($utilisateur, $input->getArgument('password')));

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        $io->success(sprintf("Utilisateur '%s' cree%s.", $utilisateur->getUsername(), $libelle));

        return Command::SUCCESS;
    }
}
