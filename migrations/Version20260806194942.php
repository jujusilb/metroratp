<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806194942 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Cree la table utilisateur (username/email/password obligatoires et uniques, coordonnees facultatives). Le mot de passe est toujours stocke hache (voir UtilisateurController).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, prenom VARCHAR(100) DEFAULT NULL, nom VARCHAR(100) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, adresse_ligne1 VARCHAR(255) DEFAULT NULL, adresse_ligne2 VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3F85E0677 (username), UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE utilisateur');
    }
}
