<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260808141348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE gestionnaire (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(100) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_transport (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(25) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ligne ADD type_transport_id INT DEFAULT NULL, ADD gestionnaire_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne ADD CONSTRAINT FK_57F0DB831E4A7B3A FOREIGN KEY (type_transport_id) REFERENCES type_transport (id)');
        $this->addSql('ALTER TABLE ligne ADD CONSTRAINT FK_57F0DB836885AC1B FOREIGN KEY (gestionnaire_id) REFERENCES gestionnaire (id)');
        $this->addSql('CREATE INDEX IDX_57F0DB831E4A7B3A ON ligne (type_transport_id)');
        $this->addSql('CREATE INDEX IDX_57F0DB836885AC1B ON ligne (gestionnaire_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE gestionnaire');
        $this->addSql('DROP TABLE type_transport');
        $this->addSql('ALTER TABLE ligne DROP FOREIGN KEY FK_57F0DB831E4A7B3A');
        $this->addSql('ALTER TABLE ligne DROP FOREIGN KEY FK_57F0DB836885AC1B');
        $this->addSql('DROP INDEX IDX_57F0DB831E4A7B3A ON ligne');
        $this->addSql('DROP INDEX IDX_57F0DB836885AC1B ON ligne');
        $this->addSql('ALTER TABLE ligne DROP type_transport_id, DROP gestionnaire_id');
    }
}
