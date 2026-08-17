<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute StyleAcces (style physique d'un accès, ex: édicule Guimard) et Acces.styleAcces";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE style_acces (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE acces ADD style_acces_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE acces ADD CONSTRAINT FK_D0F43B10DDD76786 FOREIGN KEY (style_acces_id) REFERENCES style_acces (id)');
        $this->addSql('CREATE INDEX IDX_D0F43B10DDD76786 ON acces (style_acces_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE acces DROP FOREIGN KEY FK_D0F43B10DDD76786');
        $this->addSql('DROP INDEX IDX_D0F43B10DDD76786 ON acces');
        $this->addSql('ALTER TABLE acces DROP style_acces_id');
        $this->addSql('DROP TABLE style_acces');
    }
}
