<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute Acces.estEntree/estSortie (AccIsEntry/AccIsExit du dataset IDFM)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE acces ADD est_entree TINYINT(1) DEFAULT NULL, ADD est_sortie TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE acces DROP est_entree, DROP est_sortie');
    }
}
