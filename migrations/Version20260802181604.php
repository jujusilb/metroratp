<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802181604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute directionA/directionB (nullable) sur correspondance, pour preciser une correspondance quand la distance varie selon le quai (ex: Chatelet 4<->14). Table vide, pas de migration de donnees.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX correspondance_unique_paire ON correspondance');
        $this->addSql('ALTER TABLE correspondance ADD direction_a_id INT DEFAULT NULL, ADD direction_b_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE correspondance ADD CONSTRAINT FK_A562D1E793BC9C10 FOREIGN KEY (direction_a_id) REFERENCES direction (id)');
        $this->addSql('ALTER TABLE correspondance ADD CONSTRAINT FK_A562D1E7810933FE FOREIGN KEY (direction_b_id) REFERENCES direction (id)');
        $this->addSql('CREATE INDEX IDX_A562D1E793BC9C10 ON correspondance (direction_a_id)');
        $this->addSql('CREATE INDEX IDX_A562D1E7810933FE ON correspondance (direction_b_id)');
        $this->addSql('CREATE UNIQUE INDEX correspondance_unique_paire ON correspondance (desserte_a_id, desserte_b_id, direction_a_id, direction_b_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE correspondance DROP FOREIGN KEY FK_A562D1E793BC9C10');
        $this->addSql('ALTER TABLE correspondance DROP FOREIGN KEY FK_A562D1E7810933FE');
        $this->addSql('DROP INDEX IDX_A562D1E793BC9C10 ON correspondance');
        $this->addSql('DROP INDEX IDX_A562D1E7810933FE ON correspondance');
        $this->addSql('DROP INDEX correspondance_unique_paire ON correspondance');
        $this->addSql('ALTER TABLE correspondance DROP direction_a_id, DROP direction_b_id');
        $this->addSql('CREATE UNIQUE INDEX correspondance_unique_paire ON correspondance (desserte_a_id, desserte_b_id)');
    }
}
