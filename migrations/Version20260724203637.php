<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260724203637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE desserte (id INT AUTO_INCREMENT NOT NULL, date_ouverture DATE DEFAULT NULL, station_id INT DEFAULT NULL, ligne_id INT DEFAULT NULL, style_station_id INT DEFAULT NULL, INDEX IDX_F6CF653021BDB235 (station_id), INDEX IDX_F6CF65305A438E76 (ligne_id), INDEX IDX_F6CF6530DFC53468 (style_station_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE materiel (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(50) NOT NULL, annee_production VARCHAR(5) DEFAULT NULL, type_materiel_id INT DEFAULT NULL, INDEX IDX_18D2B0915D91DD3E (type_materiel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mission (id INT AUTO_INCREMENT NOT NULL, numero INT DEFAULT NULL, service_id INT NOT NULL, troncon_id INT NOT NULL, sens_id INT NOT NULL, INDEX IDX_9067F23CED5CA9E6 (service_id), INDEX IDX_9067F23CC7C42212 (troncon_id), INDEX IDX_9067F23CDE5D515D (sens_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE troncon (id INT AUTO_INCREMENT NOT NULL, parcours VARCHAR(15) DEFAULT NULL, depart_id INT DEFAULT NULL, arrivee_id INT DEFAULT NULL, type_troncon_id INT DEFAULT NULL, INDEX IDX_1C4A96EAE02FE4B (depart_id), INDEX IDX_1C4A96EEAF07E42 (arrivee_id), INDEX IDX_1C4A96EDAC7F437 (type_troncon_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE desserte ADD CONSTRAINT FK_F6CF653021BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
        $this->addSql('ALTER TABLE desserte ADD CONSTRAINT FK_F6CF65305A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
        $this->addSql('ALTER TABLE desserte ADD CONSTRAINT FK_F6CF6530DFC53468 FOREIGN KEY (style_station_id) REFERENCES style_station (id)');
        $this->addSql('ALTER TABLE materiel ADD CONSTRAINT FK_18D2B0915D91DD3E FOREIGN KEY (type_materiel_id) REFERENCES type_materiel (id)');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23CED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23CC7C42212 FOREIGN KEY (troncon_id) REFERENCES troncon (id)');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23CDE5D515D FOREIGN KEY (sens_id) REFERENCES sens (id)');
        $this->addSql('ALTER TABLE troncon ADD CONSTRAINT FK_1C4A96EAE02FE4B FOREIGN KEY (depart_id) REFERENCES desserte (id)');
        $this->addSql('ALTER TABLE troncon ADD CONSTRAINT FK_1C4A96EEAF07E42 FOREIGN KEY (arrivee_id) REFERENCES desserte (id)');
        $this->addSql('ALTER TABLE troncon ADD CONSTRAINT FK_1C4A96EDAC7F437 FOREIGN KEY (type_troncon_id) REFERENCES type_troncon (id)');
        $this->addSql('ALTER TABLE acces CHANGE label label VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE ligne CHANGE label label VARCHAR(5) NOT NULL');
        $this->addSql('ALTER TABLE materiel_ligne ADD CONSTRAINT FK_855D793716880AAF FOREIGN KEY (materiel_id) REFERENCES materiel (id)');
        $this->addSql('ALTER TABLE materiel_ligne ADD CONSTRAINT FK_855D79375A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
        $this->addSql('DROP INDEX materiel_ligne_materiel ON materiel_ligne');
        $this->addSql('CREATE INDEX IDX_855D793716880AAF ON materiel_ligne (materiel_id)');
        $this->addSql('DROP INDEX materiel_ligne_ligne ON materiel_ligne');
        $this->addSql('CREATE INDEX IDX_855D79375A438E76 ON materiel_ligne (ligne_id)');
        $this->addSql('ALTER TABLE sens CHANGE label label VARCHAR(25) NOT NULL');
        $this->addSql('ALTER TABLE service CHANGE label label VARCHAR(25) NOT NULL');
        $this->addSql('ALTER TABLE sortie ADD CONSTRAINT FK_3C3FD3F2FC05BFAD FOREIGN KEY (acces_id) REFERENCES acces (id)');
        $this->addSql('ALTER TABLE sortie ADD CONSTRAINT FK_3C3FD3F221BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
        $this->addSql('DROP INDEX sortie_acces ON sortie');
        $this->addSql('CREATE INDEX IDX_3C3FD3F2FC05BFAD ON sortie (acces_id)');
        $this->addSql('DROP INDEX sortie_station ON sortie');
        $this->addSql('CREATE INDEX IDX_3C3FD3F221BDB235 ON sortie (station_id)');
        $this->addSql('ALTER TABLE station CHANGE label label VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE style_station CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE label label VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE type_materiel CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE label label VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE type_troncon CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE label label VARCHAR(25) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE desserte DROP FOREIGN KEY FK_F6CF653021BDB235');
        $this->addSql('ALTER TABLE desserte DROP FOREIGN KEY FK_F6CF65305A438E76');
        $this->addSql('ALTER TABLE desserte DROP FOREIGN KEY FK_F6CF6530DFC53468');
        $this->addSql('ALTER TABLE materiel DROP FOREIGN KEY FK_18D2B0915D91DD3E');
        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23CED5CA9E6');
        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23CC7C42212');
        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23CDE5D515D');
        $this->addSql('ALTER TABLE troncon DROP FOREIGN KEY FK_1C4A96EAE02FE4B');
        $this->addSql('ALTER TABLE troncon DROP FOREIGN KEY FK_1C4A96EEAF07E42');
        $this->addSql('ALTER TABLE troncon DROP FOREIGN KEY FK_1C4A96EDAC7F437');
        $this->addSql('DROP TABLE desserte');
        $this->addSql('DROP TABLE materiel');
        $this->addSql('DROP TABLE mission');
        $this->addSql('DROP TABLE troncon');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE acces CHANGE label label VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne CHANGE label label VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE materiel_ligne DROP FOREIGN KEY FK_855D793716880AAF');
        $this->addSql('ALTER TABLE materiel_ligne DROP FOREIGN KEY FK_855D79375A438E76');
        $this->addSql('DROP INDEX idx_855d79375a438e76 ON materiel_ligne');
        $this->addSql('CREATE INDEX materiel_ligne_ligne ON materiel_ligne (ligne_id)');
        $this->addSql('DROP INDEX idx_855d793716880aaf ON materiel_ligne');
        $this->addSql('CREATE INDEX materiel_ligne_materiel ON materiel_ligne (materiel_id)');
        $this->addSql('ALTER TABLE materiel_ligne ADD CONSTRAINT FK_855D793716880AAF FOREIGN KEY (materiel_id) REFERENCES materiel (id)');
        $this->addSql('ALTER TABLE materiel_ligne ADD CONSTRAINT FK_855D79375A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
        $this->addSql('ALTER TABLE sens CHANGE label label VARCHAR(25) DEFAULT NULL');
        $this->addSql('ALTER TABLE service CHANGE label label VARCHAR(25) DEFAULT NULL');
        $this->addSql('ALTER TABLE sortie DROP FOREIGN KEY FK_3C3FD3F2FC05BFAD');
        $this->addSql('ALTER TABLE sortie DROP FOREIGN KEY FK_3C3FD3F221BDB235');
        $this->addSql('DROP INDEX idx_3c3fd3f2fc05bfad ON sortie');
        $this->addSql('CREATE INDEX sortie_acces ON sortie (acces_id)');
        $this->addSql('DROP INDEX idx_3c3fd3f221bdb235 ON sortie');
        $this->addSql('CREATE INDEX sortie_station ON sortie (station_id)');
        $this->addSql('ALTER TABLE sortie ADD CONSTRAINT FK_3C3FD3F2FC05BFAD FOREIGN KEY (acces_id) REFERENCES acces (id)');
        $this->addSql('ALTER TABLE sortie ADD CONSTRAINT FK_3C3FD3F221BDB235 FOREIGN KEY (station_id) REFERENCES station (id)');
        $this->addSql('ALTER TABLE station CHANGE label label VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE style_station CHANGE id id INT NOT NULL, CHANGE label label VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE type_materiel CHANGE id id INT NOT NULL, CHANGE label label VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE type_troncon CHANGE id id INT NOT NULL, CHANGE label label VARCHAR(25) DEFAULT NULL');
    }
}
