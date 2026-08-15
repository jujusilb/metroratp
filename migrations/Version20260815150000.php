<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE acces ADD distance_marche_metres DOUBLE PRECISION DEFAULT NULL, ADD temps_marche_secondes INT DEFAULT NULL, ADD nombre_marches INT DEFAULT NULL, ADD pente_max_pourcent DOUBLE PRECISION DEFAULT NULL, ADD largeur_min_metres DOUBLE PRECISION DEFAULT NULL, ADD signalisation VARCHAR(150) DEFAULT NULL, ADD signalisation_inverse VARCHAR(150) DEFAULT NULL, ADD cheminement_bidirectionnel TINYINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE acces DROP distance_marche_metres, DROP temps_marche_secondes, DROP nombre_marches, DROP pente_max_pourcent, DROP largeur_min_metres, DROP signalisation, DROP signalisation_inverse, DROP cheminement_bidirectionnel');
    }
}
