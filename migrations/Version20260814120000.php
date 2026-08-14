<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260814120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(10) NOT NULL, secteur VARCHAR(150) NOT NULL, departements VARCHAR(50) DEFAULT NULL, url_pdf VARCHAR(500) NOT NULL, url_fiche VARCHAR(500) DEFAULT NULL, taille_fichier_mo DOUBLE PRECISION DEFAULT NULL, date_publication VARCHAR(20) DEFAULT NULL, format VARCHAR(10) DEFAULT NULL, UNIQUE INDEX UNIQ_DD5A5B7DF55AE19E (numero), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE station ADD plan_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE station ADD CONSTRAINT FK_9F39F8B1E899029B FOREIGN KEY (plan_id) REFERENCES plan (id)');
        $this->addSql('CREATE INDEX IDX_9F39F8B1E899029B ON station (plan_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE station DROP FOREIGN KEY FK_9F39F8B1E899029B');
        $this->addSql('DROP TABLE plan');
        $this->addSql('DROP INDEX IDX_9F39F8B1E899029B ON station');
        $this->addSql('ALTER TABLE station DROP plan_id');
    }
}
