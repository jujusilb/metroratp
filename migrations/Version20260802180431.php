<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802180431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduit la table direction (referentiel normalise des directions par ligne) et migre mission.direction_id pour pointer vers direction au lieu de desserte, sans perte de donnees.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE direction (id INT AUTO_INCREMENT NOT NULL, ligne_id INT NOT NULL, desserte_terminus_id INT NOT NULL, INDEX IDX_3E4AD1B35A438E76 (ligne_id), INDEX IDX_3E4AD1B37D2E4BDF (desserte_terminus_id), UNIQUE INDEX direction_unique_ligne_terminus (ligne_id, desserte_terminus_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE direction ADD CONSTRAINT FK_3E4AD1B35A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
        $this->addSql('ALTER TABLE direction ADD CONSTRAINT FK_3E4AD1B37D2E4BDF FOREIGN KEY (desserte_terminus_id) REFERENCES desserte (id)');

        // Peuple "direction" depuis les couples (ligne, terminus) distincts deja presents dans
        // les 892 missions existantes (mission.direction_id pointe encore vers desserte ici).
        $this->addSql('INSERT INTO direction (ligne_id, desserte_terminus_id) SELECT DISTINCT d.ligne_id, m.direction_id FROM mission m INNER JOIN desserte d ON d.id = m.direction_id');

        // Colonne temporaire, peuplee par jointure vers la nouvelle table direction, avant de
        // remplacer l'ancienne colonne (pas de UPDATE en place possible sur une FK cible differente).
        $this->addSql('ALTER TABLE mission ADD direction_ref_id INT DEFAULT NULL');
        $this->addSql('UPDATE mission m INNER JOIN desserte d ON d.id = m.direction_id INNER JOIN direction dir ON dir.ligne_id = d.ligne_id AND dir.desserte_terminus_id = m.direction_id SET m.direction_ref_id = dir.id');

        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23CAF73D997');
        $this->addSql('ALTER TABLE mission DROP direction_id');
        $this->addSql('ALTER TABLE mission CHANGE direction_ref_id direction_id INT NOT NULL');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23CAF73D997 FOREIGN KEY (direction_id) REFERENCES direction (id)');
        $this->addSql('CREATE INDEX IDX_9067F23CAF73D997 ON mission (direction_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mission ADD direction_ref_id INT DEFAULT NULL');
        $this->addSql('UPDATE mission m INNER JOIN direction dir ON dir.id = m.direction_id SET m.direction_ref_id = dir.desserte_terminus_id');

        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23CAF73D997');
        $this->addSql('DROP INDEX IDX_9067F23CAF73D997 ON mission');
        $this->addSql('ALTER TABLE mission DROP direction_id');
        $this->addSql('ALTER TABLE mission CHANGE direction_ref_id direction_id INT NOT NULL');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23CAF73D997 FOREIGN KEY (direction_id) REFERENCES desserte (id)');

        $this->addSql('ALTER TABLE direction DROP FOREIGN KEY FK_3E4AD1B35A438E76');
        $this->addSql('ALTER TABLE direction DROP FOREIGN KEY FK_3E4AD1B37D2E4BDF');
        $this->addSql('DROP TABLE direction');
    }
}
