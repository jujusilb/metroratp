<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reorganise le modele ArT (voir discussion avec l'utilisateur, 2026-08-20) : une Station est
 * unique par son nom et sa position, une Desserte = Station + Ligne. ArretTransporteur dupliquait
 * l'identite de Station (nom/coordonnees) et ne servait plus a rien une fois zoneTarifaire deplace
 * sur Station et l'accessibilite/signalisation deplacees sur Desserte (via sdap-arrets-associes.csv,
 * lien officiel route_id/stop_id bien plus precis que le referentiel ArT seul, qui n'a aucune
 * notion de ligne). EquipementArret est conservee (mobilier physique OSM par arret), mais
 * desormais referencee DEPUIS Desserte plutot que l'inverse : plusieurs Desserte d'une meme
 * Station (une par ligne) partagent souvent le meme arret physique (cas frequent en bus - un seul
 * poteau/banc pour plusieurs lignes), donc le meme EquipementArret, sans dupliquer les valeurs.
 */
final class Version20260820140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire ArretTransporteur (fusionne dans Station.zoneTarifaire), ajoute Desserte.estAccessible/signalisationSonore/signalisationVisuelle et Desserte.equipementArret';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE arret_transporteur DROP FOREIGN KEY FK_F2CFD35A21BDB235');
        $this->addSql('DROP TABLE arret_transporteur');
        $this->addSql('ALTER TABLE station ADD zone_tarifaire INT DEFAULT NULL');
        $this->addSql('ALTER TABLE desserte ADD est_accessible TINYINT DEFAULT NULL, ADD signalisation_sonore TINYINT DEFAULT NULL, ADD signalisation_visuelle TINYINT DEFAULT NULL, ADD equipement_arret_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE desserte ADD CONSTRAINT FK_F6CF65307720CF74 FOREIGN KEY (equipement_arret_id) REFERENCES equipement_arret (id)');
        $this->addSql('CREATE INDEX IDX_F6CF65307720CF74 ON desserte (equipement_arret_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE desserte DROP FOREIGN KEY FK_F6CF65307720CF74');
        $this->addSql('DROP INDEX IDX_F6CF65307720CF74 ON desserte');
        $this->addSql('ALTER TABLE desserte DROP est_accessible, DROP signalisation_sonore, DROP signalisation_visuelle, DROP equipement_arret_id');
        $this->addSql('ALTER TABLE station DROP zone_tarifaire');
        $this->addSql('CREATE TABLE arret_transporteur (id INT AUTO_INCREMENT NOT NULL, art_id INT NOT NULL, nom VARCHAR(150) NOT NULL, ville VARCHAR(100) DEFAULT NULL, type VARCHAR(20) NOT NULL, zone_tarifaire INT DEFAULT NULL, est_accessible TINYINT DEFAULT NULL, signalisation_sonore TINYINT DEFAULT NULL, signalisation_visuelle TINYINT DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, station_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F2CFD35A8C25E51A (art_id), INDEX IDX_F2CFD35A21BDB235 (station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE arret_transporteur ADD CONSTRAINT FK_F2CFD35A21BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
    }
}
