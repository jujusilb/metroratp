<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute StyleEcriture (style de lettrage du nom de station sur le quai : police Parisine sur
 * plaque emaillee, nom incorpore dans la ceramique murale style CMP entre-deux-guerres...) et
 * Desserte.styleEcriture, meme schema que StyleStation/Desserte.styleStation.
 */
final class Version20260827213509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute StyleEcriture et Desserte.styleEcriture (style de lettrage du nom de station)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE style_ecriture (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE desserte ADD style_ecriture_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE desserte ADD CONSTRAINT FK_F6CF65302123F11F FOREIGN KEY (style_ecriture_id) REFERENCES style_ecriture (id)');
        $this->addSql('CREATE INDEX IDX_F6CF65302123F11F ON desserte (style_ecriture_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE desserte DROP FOREIGN KEY FK_F6CF65302123F11F');
        $this->addSql('DROP TABLE style_ecriture');
        $this->addSql('DROP INDEX IDX_F6CF65302123F11F ON desserte');
        $this->addSql('ALTER TABLE desserte DROP style_ecriture_id');
    }
}
