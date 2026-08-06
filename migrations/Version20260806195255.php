<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806195255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne roles (JSON) a utilisateur, necessaire a UserInterface pour la connexion.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP roles');
    }
}
