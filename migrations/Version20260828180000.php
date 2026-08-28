<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remplace Sortie (table de jointure Acces<->Station sans aucune donnee propre - juste 2 cles
 * etrangeres) par un vrai ManyToMany Doctrine (acces_station) : suite a une remarque utilisateur,
 * cette page CRUD dediee etait purement redondante (chaque cote affiche deja l'autre reciproquement
 * sur sa propre fiche). Migre les 2513 liens existants avant de supprimer l'ancienne table.
 */
final class Version20260828180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace la table sortie par acces_station (ManyToMany Acces<->Station)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE acces_station (acces_id INT NOT NULL, station_id INT NOT NULL, INDEX IDX_3A58FD19FC05BFAD (acces_id), INDEX IDX_3A58FD1921BDB235 (station_id), PRIMARY KEY(acces_id, station_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE acces_station ADD CONSTRAINT FK_3A58FD19FC05BFAD FOREIGN KEY (acces_id) REFERENCES acces (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE acces_station ADD CONSTRAINT FK_3A58FD1921BDB235 FOREIGN KEY (station_id) REFERENCES station (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO acces_station (acces_id, station_id) SELECT acces_id, station_id FROM sortie WHERE acces_id IS NOT NULL AND station_id IS NOT NULL');
        $this->addSql('DROP TABLE sortie');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sortie (id INT AUTO_INCREMENT NOT NULL, acces_id INT DEFAULT NULL, station_id INT DEFAULT NULL, INDEX IDX_sortie_acces (acces_id), INDEX IDX_sortie_station (station_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE sortie ADD CONSTRAINT FK_sortie_acces FOREIGN KEY (acces_id) REFERENCES acces (id)');
        $this->addSql('ALTER TABLE sortie ADD CONSTRAINT FK_sortie_station FOREIGN KEY (station_id) REFERENCES station (id)');
        $this->addSql('INSERT INTO sortie (acces_id, station_id) SELECT acces_id, station_id FROM acces_station');
        $this->addSql('ALTER TABLE acces_station DROP FOREIGN KEY FK_3A58FD19FC05BFAD');
        $this->addSql('ALTER TABLE acces_station DROP FOREIGN KEY FK_3A58FD1921BDB235');
        $this->addSql('DROP TABLE acces_station');
    }
}
