<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute raison_desserte : meme mecanisme que raison_station (Raison), mais au niveau Desserte -
 * une Station peut rester active (vrai service aujourd'hui, ex: un arret de bus) alors qu'une de
 * ses Desserte precises est definitivement morte (ex: un quai de metro jamais mis en service ou
 * ferme sans reouverture) - remarque utilisateur sur les "stations fantomes" (voir TODO.md).
 */
final class Version20260829100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table raison_desserte (Raison au niveau Desserte, pas seulement Station)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE raison_desserte (raison_id INT NOT NULL, desserte_id INT NOT NULL, INDEX IDX_806DE7F4EE1E550F (raison_id), INDEX IDX_806DE7F4517B6095 (desserte_id), PRIMARY KEY (raison_id, desserte_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE raison_desserte ADD CONSTRAINT FK_806DE7F4EE1E550F FOREIGN KEY (raison_id) REFERENCES raison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE raison_desserte ADD CONSTRAINT FK_806DE7F4517B6095 FOREIGN KEY (desserte_id) REFERENCES desserte (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE raison_desserte');
    }
}
