<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Etend Ville a 4 entites supplementaires (Defibrillateur, EquipementArret, PointDeVente,
 * Utilisateur), en plus de Station deja faite - voir documentation/TODO.md.
 */
final class Version20260824100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute villeRef sur Defibrillateur, EquipementArret, PointDeVente, Utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE defibrillateur ADD ville_ref_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE defibrillateur ADD CONSTRAINT FK_AE73E18D37540B03 FOREIGN KEY (ville_ref_id) REFERENCES ville (id)');
        $this->addSql('CREATE INDEX IDX_AE73E18D37540B03 ON defibrillateur (ville_ref_id)');

        $this->addSql('ALTER TABLE equipement_arret ADD ville_ref_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_arret ADD CONSTRAINT FK_9DE7BB3B37540B03 FOREIGN KEY (ville_ref_id) REFERENCES ville (id)');
        $this->addSql('CREATE INDEX IDX_9DE7BB3B37540B03 ON equipement_arret (ville_ref_id)');

        $this->addSql('ALTER TABLE point_de_vente ADD ville_ref_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE point_de_vente ADD CONSTRAINT FK_C9182F7B37540B03 FOREIGN KEY (ville_ref_id) REFERENCES ville (id)');
        $this->addSql('CREATE INDEX IDX_C9182F7B37540B03 ON point_de_vente (ville_ref_id)');

        $this->addSql('ALTER TABLE utilisateur ADD ville_ref_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B337540B03 FOREIGN KEY (ville_ref_id) REFERENCES ville (id)');
        $this->addSql('CREATE INDEX IDX_1D1C63B337540B03 ON utilisateur (ville_ref_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE defibrillateur DROP FOREIGN KEY FK_AE73E18D37540B03');
        $this->addSql('DROP INDEX IDX_AE73E18D37540B03 ON defibrillateur');
        $this->addSql('ALTER TABLE defibrillateur DROP ville_ref_id');

        $this->addSql('ALTER TABLE equipement_arret DROP FOREIGN KEY FK_9DE7BB3B37540B03');
        $this->addSql('DROP INDEX IDX_9DE7BB3B37540B03 ON equipement_arret');
        $this->addSql('ALTER TABLE equipement_arret DROP ville_ref_id');

        $this->addSql('ALTER TABLE point_de_vente DROP FOREIGN KEY FK_C9182F7B37540B03');
        $this->addSql('DROP INDEX IDX_C9182F7B37540B03 ON point_de_vente');
        $this->addSql('ALTER TABLE point_de_vente DROP ville_ref_id');

        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B337540B03');
        $this->addSql('DROP INDEX IDX_1D1C63B337540B03 ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur DROP ville_ref_id');
    }
}
