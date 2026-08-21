<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute Desserte.climatisation (dataset sdap-arrets-associes.csv, champ
 * Extensions.ServiceFacilitySet.ClimateControlList - meme rattachement par couple
 * Station/Ligne que estAccessible/signalisationSonore/signalisationVisuelle, voir
 * app:importer-accessibilite-dessertes).
 */
final class Version20260821160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Desserte.climatisation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE desserte ADD climatisation VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE desserte DROP climatisation');
    }
}
