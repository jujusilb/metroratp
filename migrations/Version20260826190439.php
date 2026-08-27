<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute Utilisateur.isVerified (verification d'email a l'inscription, voir EmailVerifier /
 * InscriptionController / VerificationEmailController). Faux par defaut : un compte existant est
 * considere non verifie tant qu'il n'a pas ete migre manuellement si besoin.
 */
final class Version20260826190439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute Utilisateur.isVerified pour la verification d'email a l'inscription";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD is_verified TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP is_verified');
    }
}
