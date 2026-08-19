<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute EquipementArret (equipements OSM par arret physique, dataset ecarts-arrets-referentiel-et-openstreetmap)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipement_arret (id INT AUTO_INCREMENT NOT NULL, art_id INT NOT NULL, nom VARCHAR(150) NOT NULL, ville VARCHAR(100) DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, accessible_fauteuil_roulant TINYINT DEFAULT NULL, banc TINYINT DEFAULT NULL, poubelle TINYINT DEFAULT NULL, eclairage TINYINT DEFAULT NULL, abri TINYINT DEFAULT NULL, bande_tactile TINYINT DEFAULT NULL, distance_referentiel_osm INT DEFAULT NULL, station_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_9DE7BB3B8C25E51A (art_id), INDEX IDX_9DE7BB3B21BDB235 (station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE equipement_arret ADD CONSTRAINT FK_9DE7BB3B21BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipement_arret DROP FOREIGN KEY FK_9DE7BB3B21BDB235');
        $this->addSql('DROP TABLE equipement_arret');
    }
}
