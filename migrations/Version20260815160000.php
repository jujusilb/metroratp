<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sanitaire (id INT AUTO_INCREMENT NOT NULL, ligne_label VARCHAR(30) DEFAULT NULL, label VARCHAR(150) NOT NULL, accessible_public TINYINT DEFAULT NULL, tarif VARCHAR(20) DEFAULT NULL, acces_pass_navigo_ticket_t TINYINT DEFAULT NULL, acces_bouton_poussoir TINYINT DEFAULT NULL, en_zone_controlee TINYINT DEFAULT NULL, hors_zone_controlee_station TINYINT DEFAULT NULL, hors_zone_controlee_voie_publique TINYINT DEFAULT NULL, accessibilite_pmr TINYINT DEFAULT NULL, localisation LONGTEXT DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, gestionnaire VARCHAR(100) DEFAULT NULL, station_id INT DEFAULT NULL, INDEX IDX_2F0B8AD421BDB235 (station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE sanitaire ADD CONSTRAINT FK_2F0B8AD421BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sanitaire DROP FOREIGN KEY FK_2F0B8AD421BDB235');
        $this->addSql('DROP TABLE sanitaire');
    }
}
