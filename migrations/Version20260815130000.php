<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE projet_arret (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(150) NOT NULL, nom_projet VARCHAR(150) NOT NULL, operation VARCHAR(200) DEFAULT NULL, nature VARCHAR(30) DEFAULT NULL, mode VARCHAR(10) DEFAULT NULL, statut VARCHAR(10) DEFAULT NULL, phase VARCHAR(10) DEFAULT NULL, creation TINYINT NOT NULL, prolongement TINYINT NOT NULL, amelioration TINYINT NOT NULL, terminus TINYINT NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE projet_arret');
    }
}
