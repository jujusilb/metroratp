<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute Troncon.varianteMaillage, pour etiqueter a la main les troncons qui referment un vrai
 * maillage (plusieurs itineraires physiques distincts entre deux memes points, ex. RER D) - voir
 * documentation/TODO.md.
 */
final class Version20260825201506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute variante_maillage sur Troncon';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE troncon ADD variante_maillage VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE troncon DROP variante_maillage');
    }
}
