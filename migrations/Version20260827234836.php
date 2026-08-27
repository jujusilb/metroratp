<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute Raison (catalogue des raisons pour lesquelles une Station est consideree inactive) et
 * sa relation ManyToMany avec Station (raison_station) - pas de champ Station.actif separe,
 * la seule presence d'une Raison liee signifie "inactive".
 */
final class Version20260827234836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute Raison et la relation ManyToMany raison_station (stations inactives)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE raison (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(150) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE raison_station (raison_id INT NOT NULL, station_id INT NOT NULL, INDEX IDX_E1FAACF1EE1E550F (raison_id), INDEX IDX_E1FAACF121BDB235 (station_id), PRIMARY KEY (raison_id, station_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE raison_station ADD CONSTRAINT FK_E1FAACF1EE1E550F FOREIGN KEY (raison_id) REFERENCES raison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE raison_station ADD CONSTRAINT FK_E1FAACF121BDB235 FOREIGN KEY (station_id) REFERENCES station (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE raison_station DROP FOREIGN KEY FK_E1FAACF1EE1E550F');
        $this->addSql('ALTER TABLE raison_station DROP FOREIGN KEY FK_E1FAACF121BDB235');
        $this->addSql('DROP TABLE raison_station');
        $this->addSql('DROP TABLE raison');
    }
}
