<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260726130746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE periode_ouverture (id INT AUTO_INCREMENT NOT NULL, ordre INT NOT NULL, ouverture DATE DEFAULT NULL, fermeture DATE DEFAULT NULL, desserte_id INT NOT NULL, INDEX IDX_AC16C1AD517B6095 (desserte_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE periode_ouverture ADD CONSTRAINT FK_AC16C1AD517B6095 FOREIGN KEY (desserte_id) REFERENCES desserte (id)');

        // Reprend chaque desserte comme une periode d'ouverture unique (ordre 1), en conservant
        // les dates deja connues (315/392) plutot que de les perdre en droppant les colonnes.
        $this->addSql('INSERT INTO periode_ouverture (desserte_id, ordre, ouverture, fermeture) SELECT id, 1, date_ouverture, date_fermeture FROM desserte');

        $this->addSql('ALTER TABLE desserte DROP date_ouverture, DROP date_fermeture');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE desserte ADD date_ouverture DATE DEFAULT NULL, ADD date_fermeture DATE DEFAULT NULL');

        // Restaure les dates depuis la premiere periode (ordre 1) de chaque desserte.
        $this->addSql('UPDATE desserte d INNER JOIN periode_ouverture p ON p.desserte_id = d.id AND p.ordre = 1 SET d.date_ouverture = p.ouverture, d.date_fermeture = p.fermeture');

        $this->addSql('ALTER TABLE periode_ouverture DROP FOREIGN KEY FK_AC16C1AD517B6095');
        $this->addSql('DROP TABLE periode_ouverture');
    }
}
