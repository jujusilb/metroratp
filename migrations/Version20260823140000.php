<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute PositionRame.directionId/terminusReel/prochaineStation : permet de departager les 2
 * conseils opposes qu'une meme Station+Ligne peut porter, selon le sens de circulation reellement
 * emprunte (voir documentation/TODO.md, "Conseils de position dans la rame").
 */
final class Version20260823140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute PositionRame.directionId/terminusReel/prochaineStation (sens de circulation)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position_rame ADD direction_id INT DEFAULT NULL, ADD terminus_reel VARCHAR(100) DEFAULT NULL, ADD prochaine_station_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE position_rame ADD CONSTRAINT FK_5774B87F32FC53D FOREIGN KEY (prochaine_station_id) REFERENCES station (id)');
        $this->addSql('CREATE INDEX IDX_5774B87F32FC53D ON position_rame (prochaine_station_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position_rame DROP FOREIGN KEY FK_5774B87F32FC53D');
        $this->addSql('DROP INDEX IDX_5774B87F32FC53D ON position_rame');
        $this->addSql('ALTER TABLE position_rame DROP direction_id, DROP terminus_reel, DROP prochaine_station_id');
    }
}
