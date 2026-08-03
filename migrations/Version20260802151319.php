<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802151319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE correspondance (id INT AUTO_INCREMENT NOT NULL, distance INT DEFAULT NULL, in_zone TINYINT NOT NULL, desserte_a_id INT NOT NULL, desserte_b_id INT NOT NULL, INDEX IDX_A562D1E713370BB2 (desserte_a_id), INDEX IDX_A562D1E7182A45C (desserte_b_id), UNIQUE INDEX correspondance_unique_paire (desserte_a_id, desserte_b_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE correspondance ADD CONSTRAINT FK_A562D1E713370BB2 FOREIGN KEY (desserte_a_id) REFERENCES desserte (id)');
        $this->addSql('ALTER TABLE correspondance ADD CONSTRAINT FK_A562D1E7182A45C FOREIGN KEY (desserte_b_id) REFERENCES desserte (id)');
        // Doctrine ne genere pas les CHECK constraints depuis les attributs d'entite : ajoutee
        // a la main. Impose l'ordre canonique de la paire (voir Correspondance::normaliserOrdre,
        // appelee en PrePersist/PreUpdate), pour qu'une correspondance A<->B ne soit jamais
        // dupliquee en (A,B) et (B,A).
        $this->addSql('ALTER TABLE correspondance ADD CONSTRAINT correspondance_check_ordre CHECK (desserte_a_id < desserte_b_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE correspondance DROP FOREIGN KEY FK_A562D1E713370BB2');
        $this->addSql('ALTER TABLE correspondance DROP FOREIGN KEY FK_A562D1E7182A45C');
        $this->addSql('DROP TABLE correspondance');
    }
}
