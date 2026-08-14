<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260814130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pole_echange (id INT AUTO_INCREMENT NOT NULL, code_externe VARCHAR(20) NOT NULL, label VARCHAR(150) NOT NULL, UNIQUE INDEX UNIQ_E581D8DBA4D59F2 (code_externe), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE station ADD pole_echange_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE station ADD CONSTRAINT FK_9F39F8B134C3E3A5 FOREIGN KEY (pole_echange_id) REFERENCES pole_echange (id)');
        $this->addSql('CREATE INDEX IDX_9F39F8B134C3E3A5 ON station (pole_echange_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE station DROP FOREIGN KEY FK_9F39F8B134C3E3A5');
        $this->addSql('DROP TABLE pole_echange');
        $this->addSql('DROP INDEX IDX_9F39F8B134C3E3A5 ON station');
        $this->addSql('ALTER TABLE station DROP pole_echange_id');
    }
}
