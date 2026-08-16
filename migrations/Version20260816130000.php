<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE statut_tache (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(30) NOT NULL, UNIQUE INDEX UNIQ_A17A4F0BEA750E8 (label), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tache (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, datetime_creation DATETIME NOT NULL, datetime_action DATETIME DEFAULT NULL, datetime_achevement DATETIME DEFAULT NULL, statut_id INT NOT NULL, INDEX IDX_93872075F6203804 (statut_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE etape (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, datetime_creation DATETIME NOT NULL, datetime_achevement DATETIME DEFAULT NULL, tache_id INT NOT NULL, INDEX IDX_285F75DDD2235D39 (tache_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT FK_93872075F6203804 FOREIGN KEY (statut_id) REFERENCES statut_tache (id)');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT FK_285F75DDD2235D39 FOREIGN KEY (tache_id) REFERENCES tache (id)');
        $this->addSql("INSERT INTO statut_tache (label) VALUES ('A_FAIRE'), ('EN_COURS'), ('SUSPENDUE'), ('ACHEVEE')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY FK_285F75DDD2235D39');
        $this->addSql('ALTER TABLE tache DROP FOREIGN KEY FK_93872075F6203804');
        $this->addSql('DROP TABLE etape');
        $this->addSql('DROP TABLE tache');
        $this->addSql('DROP TABLE statut_tache');
    }
}
