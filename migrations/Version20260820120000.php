<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute Acces.aEscalierMecanique/aAscenseur (rattachement par proximite a OpenStreetMap)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE acces ADD a_escalier_mecanique TINYINT DEFAULT NULL, ADD a_ascenseur TINYINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE acces DROP a_escalier_mecanique, DROP a_ascenseur');
    }
}
