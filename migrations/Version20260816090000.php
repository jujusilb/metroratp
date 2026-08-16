<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sanisette_publique (id INT AUTO_INCREMENT NOT NULL, arrondissement VARCHAR(10) DEFAULT NULL, type VARCHAR(30) NOT NULL, statut VARCHAR(20) NOT NULL, adresse VARCHAR(150) NOT NULL, horaire VARCHAR(50) DEFAULT NULL, acces_pmr TINYINT DEFAULT NULL, relais_bebe TINYINT DEFAULT NULL, url_fiche_equipement VARCHAR(255) DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, gestionnaire VARCHAR(100) DEFAULT NULL, station_id INT DEFAULT NULL, INDEX IDX_9C0AEEBB21BDB235 (station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE sanisette_publique ADD CONSTRAINT FK_9C0AEEBB21BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sanisette_publique DROP FOREIGN KEY FK_9C0AEEBB21BDB235');
        $this->addSql('DROP TABLE sanisette_publique');
    }
}
