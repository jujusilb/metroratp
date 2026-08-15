<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE point_de_vente (id INT AUTO_INCREMENT NOT NULL, code_externe VARCHAR(30) NOT NULL, label VARCHAR(150) NOT NULL, type VARCHAR(30) DEFAULT NULL, adresse VARCHAR(200) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, horaires VARCHAR(150) DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, station_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_C9182F7BA4D59F2 (code_externe), INDEX IDX_C9182F7B21BDB235 (station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE point_de_vente ADD CONSTRAINT FK_C9182F7B21BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE point_de_vente DROP FOREIGN KEY FK_C9182F7B21BDB235');
        $this->addSql('DROP TABLE point_de_vente');
    }
}
