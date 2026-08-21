<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute TronconDesserte.dureeReelleSecondes : permet un temps de trajet asymetrique par sens de
 * circulation (quais decales, ex: Liege sur la ligne 13 metro) plutot que la seule valeur symetrique
 * partagee de Troncon.dureeReelleSecondes. Voir app:importer-durees-troncon et TrajetFinder.
 */
final class Version20260821180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute TronconDesserte.dureeReelleSecondes (temps de trajet asymetrique par sens)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE troncon_desserte ADD duree_reelle_secondes INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE troncon_desserte DROP duree_reelle_secondes');
    }
}
