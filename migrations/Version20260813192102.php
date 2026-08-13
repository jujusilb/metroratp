<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813192102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE acces ADD code_externe VARCHAR(20) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D0F43B10A4D59F2 ON acces (code_externe)');
        $this->addSql('CREATE TABLE position_rame (id INT AUTO_INCREMENT NOT NULL, ligne_id INT NOT NULL, station_id INT NOT NULL, acces_id INT DEFAULT NULL, destination VARCHAR(150) NOT NULL, label_position VARCHAR(20) NOT NULL, position INT NOT NULL, position_max INT NOT NULL, equipement VARCHAR(30) DEFAULT NULL, INDEX IDX_5774B87F5A438E76 (ligne_id), INDEX IDX_5774B87F21BDB235 (station_id), INDEX IDX_5774B87FFC05BFAD (acces_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE position_rame ADD CONSTRAINT FK_5774B87F5A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
        $this->addSql('ALTER TABLE position_rame ADD CONSTRAINT FK_5774B87F21BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
        $this->addSql('ALTER TABLE position_rame ADD CONSTRAINT FK_5774B87FFC05BFAD FOREIGN KEY (acces_id) REFERENCES acces (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE position_rame DROP FOREIGN KEY FK_5774B87F5A438E76');
        $this->addSql('ALTER TABLE position_rame DROP FOREIGN KEY FK_5774B87F21BDB235');
        $this->addSql('ALTER TABLE position_rame DROP FOREIGN KEY FK_5774B87FFC05BFAD');
        $this->addSql('DROP TABLE position_rame');
        $this->addSql('DROP INDEX UNIQ_D0F43B10A4D59F2 ON acces');
        $this->addSql('ALTER TABLE acces DROP code_externe');
    }
}
