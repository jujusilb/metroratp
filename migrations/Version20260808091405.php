<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260808091405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Remplace troncon.parcours (varchar, toujours NULL, jamais utilise) par troncon.distance (metres, source GTFS shapes.txt/stop_times.txt) : distance physique fixe, independante du materiel roulant, contrairement a dureeReelleSecondes.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE troncon ADD distance DOUBLE PRECISION DEFAULT NULL, DROP parcours');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE troncon ADD parcours VARCHAR(15) DEFAULT NULL, DROP distance');
    }
}
