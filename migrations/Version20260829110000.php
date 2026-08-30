<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime raison_station : l'inactivite se marque desormais uniquement sur Desserte (voir
 * Version20260829100000 + TODO.md "stations fantomes"), plus sur Station directement -
 * Station::estActive() est desormais calculee depuis ses Desserte. ATTENTION : la commande
 * app:migrer-raison-station-vers-desserte DOIT avoir tourne avant cette migration (elle recree,
 * pour chaque Station encore taguee via l'ancien mecanisme, une Desserte generique - Ligne nulle -
 * portant la/les meme(s) Raison(s)), sans quoi ces donnees seraient perdues.
 */
final class Version20260829110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime raison_station (inactivite portee uniquement par Desserte desormais)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raison_station DROP FOREIGN KEY FK_E1FAACF121BDB235');
        $this->addSql('ALTER TABLE raison_station DROP FOREIGN KEY FK_E1FAACF1EE1E550F');
        $this->addSql('DROP TABLE raison_station');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE raison_station (raison_id INT NOT NULL, station_id INT NOT NULL, INDEX IDX_E1FAACF1EE1E550F (raison_id), INDEX IDX_E1FAACF121BDB235 (station_id), PRIMARY KEY(raison_id, station_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE raison_station ADD CONSTRAINT FK_E1FAACF1EE1E550F FOREIGN KEY (raison_id) REFERENCES raison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE raison_station ADD CONSTRAINT FK_E1FAACF121BDB235 FOREIGN KEY (station_id) REFERENCES station (id) ON DELETE CASCADE');
    }
}
