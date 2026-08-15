<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan_region (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(10) NOT NULL, ordre INT NOT NULL, label VARCHAR(150) NOT NULL, url_pdf VARCHAR(500) NOT NULL, url_fiche VARCHAR(500) DEFAULT NULL, taille_fichier_mo DOUBLE PRECISION DEFAULT NULL, date_publication VARCHAR(20) DEFAULT NULL, format VARCHAR(10) DEFAULT NULL, UNIQUE INDEX UNIQ_BB3AE904F55AE19E (numero), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE plan_region');
    }
}
