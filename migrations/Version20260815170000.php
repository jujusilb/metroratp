<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE defibrillateur (id INT AUTO_INCREMENT NOT NULL, localisation VARCHAR(150) NOT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, acces VARCHAR(30) DEFAULT NULL, acces_libre TINYINT DEFAULT NULL, complement_localisation VARCHAR(200) DEFAULT NULL, disponibilite_semaine VARCHAR(50) DEFAULT NULL, disponibilite_horaires VARCHAR(50) DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, station_id INT DEFAULT NULL, INDEX IDX_AE73E18D21BDB235 (station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE defibrillateur ADD CONSTRAINT FK_AE73E18D21BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE defibrillateur DROP FOREIGN KEY FK_AE73E18D21BDB235');
        $this->addSql('DROP TABLE defibrillateur');
    }
}
