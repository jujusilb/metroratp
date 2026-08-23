<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute Ville.codesPostaux (deja present dans documentation/geo-communes/*.geojson, oublie lors
 * de l'ajout initial de l'entite - voir app:importer-villes).
 */
final class Version20260823100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Ville.codesPostaux';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ville ADD codes_postaux JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ville DROP codes_postaux');
    }
}
