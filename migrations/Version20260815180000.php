<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fontaine_eau (id INT AUTO_INCREMENT NOT NULL, ligne_label VARCHAR(30) DEFAULT NULL, label VARCHAR(150) NOT NULL, adresse VARCHAR(200) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, commune VARCHAR(100) DEFAULT NULL, numero_acces_proche VARCHAR(20) DEFAULT NULL, nom_acces_proche VARCHAR(150) DEFAULT NULL, en_zone_controlee VARCHAR(50) DEFAULT NULL, identifiant_ratp VARCHAR(20) DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, acces_id INT DEFAULT NULL, station_id INT DEFAULT NULL, INDEX IDX_FD4F3D0DFC05BFAD (acces_id), INDEX IDX_FD4F3D0D21BDB235 (station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE fontaine_eau ADD CONSTRAINT FK_FD4F3D0DFC05BFAD FOREIGN KEY (acces_id) REFERENCES acces (id)');
        $this->addSql('ALTER TABLE fontaine_eau ADD CONSTRAINT FK_FD4F3D0D21BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fontaine_eau DROP FOREIGN KEY FK_FD4F3D0DFC05BFAD');
        $this->addSql('ALTER TABLE fontaine_eau DROP FOREIGN KEY FK_FD4F3D0D21BDB235');
        $this->addSql('DROP TABLE fontaine_eau');
    }
}
