<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_ligne (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, nom VARCHAR(150) NOT NULL, url VARCHAR(255) NOT NULL, ligne_id INT NOT NULL, UNIQUE INDEX UNIQ_3E51ADF1F47645AE (url), INDEX IDX_3E51ADF15A438E76 (ligne_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document_ligne ADD CONSTRAINT FK_3E51ADF15A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_ligne DROP FOREIGN KEY FK_3E51ADF15A438E76');
        $this->addSql('DROP TABLE document_ligne');
    }
}
