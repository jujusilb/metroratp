<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute ArretTransporteur (referentiel officiel IDFM par arret physique, dataset arrets-transporteur.csv)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE arret_transporteur (id INT AUTO_INCREMENT NOT NULL, art_id INT NOT NULL, nom VARCHAR(150) NOT NULL, ville VARCHAR(100) DEFAULT NULL, type VARCHAR(20) NOT NULL, zone_tarifaire INT DEFAULT NULL, est_accessible TINYINT DEFAULT NULL, signalisation_sonore TINYINT DEFAULT NULL, signalisation_visuelle TINYINT DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, station_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F2CFD35A8C25E51A (art_id), INDEX IDX_F2CFD35A21BDB235 (station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE arret_transporteur ADD CONSTRAINT FK_F2CFD35A21BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE arret_transporteur DROP FOREIGN KEY FK_F2CFD35A21BDB235');
        $this->addSql('DROP TABLE arret_transporteur');
    }
}
