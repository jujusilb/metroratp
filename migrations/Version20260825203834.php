<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute Automatisation (id, label - "porte de rame"/"porte paliere"/"total") et sa table de
 * liaison AutomatisationLigne (ligne_id, automatisation_id, dateDeMiseEnPlace) - voir
 * documentation/TODO.md.
 */
final class Version20260825203834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Automatisation et AutomatisationLigne';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE automatisation (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE automatisation_ligne (id INT AUTO_INCREMENT NOT NULL, date_de_mise_en_place DATE DEFAULT NULL, automatisation_id INT DEFAULT NULL, ligne_id INT DEFAULT NULL, INDEX IDX_B69AFD00ECF39A72 (automatisation_id), INDEX IDX_B69AFD005A438E76 (ligne_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE automatisation_ligne ADD CONSTRAINT FK_B69AFD00ECF39A72 FOREIGN KEY (automatisation_id) REFERENCES automatisation (id)');
        $this->addSql('ALTER TABLE automatisation_ligne ADD CONSTRAINT FK_B69AFD005A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE automatisation_ligne DROP FOREIGN KEY FK_B69AFD00ECF39A72');
        $this->addSql('ALTER TABLE automatisation_ligne DROP FOREIGN KEY FK_B69AFD005A438E76');
        $this->addSql('DROP TABLE automatisation_ligne');
        $this->addSql('DROP TABLE automatisation');
    }
}
