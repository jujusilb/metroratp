<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725230824 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace troncon.depart/arrivee (fixed direction) and mission.sens with a '
            . 'direction-agnostic troncon_desserte join table, and mission.direction (terminus desserte).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE type_desserte (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(25) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE troncon_desserte (id INT AUTO_INCREMENT NOT NULL, troncon_id INT NOT NULL, desserte_id INT NOT NULL, type_desserte_id INT NOT NULL, INDEX IDX_AA2EEFCC7C42212 (troncon_id), INDEX IDX_AA2EEFC517B6095 (desserte_id), INDEX IDX_AA2EEFC1A62B704 (type_desserte_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE troncon_desserte ADD CONSTRAINT FK_AA2EEFCC7C42212 FOREIGN KEY (troncon_id) REFERENCES troncon (id)');
        $this->addSql('ALTER TABLE troncon_desserte ADD CONSTRAINT FK_AA2EEFC517B6095 FOREIGN KEY (desserte_id) REFERENCES desserte (id)');
        $this->addSql('ALTER TABLE troncon_desserte ADD CONSTRAINT FK_AA2EEFC1A62B704 FOREIGN KEY (type_desserte_id) REFERENCES type_desserte (id)');

        // Detach mission from troncon/sens before touching those tables/columns.
        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23CC7C42212');
        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23CDE5D515D');
        $this->addSql('DROP INDEX IDX_9067F23CC7C42212 ON mission');
        $this->addSql('DROP INDEX IDX_9067F23CDE5D515D ON mission');

        // Nullable for now: existing rows need to be backfilled (see data migration script)
        // before these can be tightened to NOT NULL.
        $this->addSql('ALTER TABLE mission ADD troncon_desserte_id INT DEFAULT NULL, ADD direction_id INT DEFAULT NULL, DROP troncon_id, DROP sens_id');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23C22BA5328 FOREIGN KEY (troncon_desserte_id) REFERENCES troncon_desserte (id)');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23CAF73D997 FOREIGN KEY (direction_id) REFERENCES desserte (id)');
        $this->addSql('CREATE INDEX IDX_9067F23C22BA5328 ON mission (troncon_desserte_id)');
        $this->addSql('CREATE INDEX IDX_9067F23CAF73D997 ON mission (direction_id)');

        $this->addSql('DROP TABLE sens');

        $this->addSql('ALTER TABLE troncon DROP FOREIGN KEY FK_1C4A96EAE02FE4B');
        $this->addSql('ALTER TABLE troncon DROP FOREIGN KEY FK_1C4A96EEAF07E42');
        $this->addSql('DROP INDEX IDX_1C4A96EAE02FE4B ON troncon');
        $this->addSql('DROP INDEX IDX_1C4A96EEAF07E42 ON troncon');
        $this->addSql('ALTER TABLE troncon DROP depart_id, DROP arrivee_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE troncon ADD depart_id INT DEFAULT NULL, ADD arrivee_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE troncon ADD CONSTRAINT FK_1C4A96EAE02FE4B FOREIGN KEY (depart_id) REFERENCES desserte (id)');
        $this->addSql('ALTER TABLE troncon ADD CONSTRAINT FK_1C4A96EEAF07E42 FOREIGN KEY (arrivee_id) REFERENCES desserte (id)');
        $this->addSql('CREATE INDEX IDX_1C4A96EAE02FE4B ON troncon (depart_id)');
        $this->addSql('CREATE INDEX IDX_1C4A96EEAF07E42 ON troncon (arrivee_id)');

        $this->addSql('CREATE TABLE sens (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(25) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23C22BA5328');
        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23CAF73D997');
        $this->addSql('DROP INDEX IDX_9067F23C22BA5328 ON mission');
        $this->addSql('DROP INDEX IDX_9067F23CAF73D997 ON mission');
        $this->addSql('ALTER TABLE mission ADD troncon_id INT DEFAULT NULL, ADD sens_id INT DEFAULT NULL, DROP troncon_desserte_id, DROP direction_id');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23CC7C42212 FOREIGN KEY (troncon_id) REFERENCES troncon (id)');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23CDE5D515D FOREIGN KEY (sens_id) REFERENCES sens (id)');
        $this->addSql('CREATE INDEX IDX_9067F23CC7C42212 ON mission (troncon_id)');
        $this->addSql('CREATE INDEX IDX_9067F23CDE5D515D ON mission (sens_id)');

        $this->addSql('ALTER TABLE troncon_desserte DROP FOREIGN KEY FK_AA2EEFCC7C42212');
        $this->addSql('ALTER TABLE troncon_desserte DROP FOREIGN KEY FK_AA2EEFC517B6095');
        $this->addSql('ALTER TABLE troncon_desserte DROP FOREIGN KEY FK_AA2EEFC1A62B704');
        $this->addSql('DROP TABLE troncon_desserte');
        $this->addSql('DROP TABLE type_desserte');
    }
}
