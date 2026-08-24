<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute PointInteret (lieux remarquables a proximite d'une ou plusieurs Station - musee,
 * monument, hopital, jardin... voir documentation/scripts/extraire_points_interet.php et
 * app:importer-points-interet).
 */
final class Version20260823180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute PointInteret (lieux remarquables a proximite d une Station)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE point_interet (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(150) NOT NULL, UNIQUE INDEX UNIQ_1E559669EA750E8 (label), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE point_interet_station (point_interet_id INT NOT NULL, station_id INT NOT NULL, INDEX IDX_2B5E51FD1D5DBD66 (point_interet_id), INDEX IDX_2B5E51FD21BDB235 (station_id), PRIMARY KEY (point_interet_id, station_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE point_interet_station ADD CONSTRAINT FK_2B5E51FD1D5DBD66 FOREIGN KEY (point_interet_id) REFERENCES point_interet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE point_interet_station ADD CONSTRAINT FK_2B5E51FD21BDB235 FOREIGN KEY (station_id) REFERENCES station (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE point_interet_station DROP FOREIGN KEY FK_2B5E51FD1D5DBD66');
        $this->addSql('ALTER TABLE point_interet_station DROP FOREIGN KEY FK_2B5E51FD21BDB235');
        $this->addSql('DROP TABLE point_interet_station');
        $this->addSql('DROP TABLE point_interet');
    }
}
