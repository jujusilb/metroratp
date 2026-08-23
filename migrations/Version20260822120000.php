<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute l'entite Ville (referentiel geo.api.gouv.fr, frontieres GPS des communes - voir
 * documentation/geo-communes/ et app:importer-villes) et Station.villeRef, une relation vers
 * Ville rattachee par correspondance de nom depuis Station::ville (texte libre, inchange).
 */
final class Version20260822120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Ville (communes, frontieres GPS) et Station.villeRef';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ville (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(100) NOT NULL, code_insee VARCHAR(5) NOT NULL, frontiere JSON DEFAULT NULL, UNIQUE INDEX UNIQ_43C3D9C31649A761 (code_insee), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE station ADD ville_ref_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE station ADD CONSTRAINT FK_9F39F8B137540B03 FOREIGN KEY (ville_ref_id) REFERENCES ville (id)');
        $this->addSql('CREATE INDEX IDX_9F39F8B137540B03 ON station (ville_ref_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE station DROP FOREIGN KEY FK_9F39F8B137540B03');
        $this->addSql('DROP INDEX IDX_9F39F8B137540B03 ON station');
        $this->addSql('ALTER TABLE station DROP ville_ref_id');
        $this->addSql('DROP TABLE ville');
    }
}
