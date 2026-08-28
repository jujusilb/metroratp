<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute Depot (centre bus) + sa relation ManyToMany avec Ligne (depot_ligne) + MaterielDepot
 * (meme schema que MaterielLigne, transpose au depot) : le materiel de bus est documente par
 * depot plutot que par ligne precise, contrairement au ferroviaire - voir Depot::class.
 */
final class Version20260828150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Depot, depot_ligne (ManyToMany avec Ligne) et MaterielDepot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE depot (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(150) NOT NULL, adresse VARCHAR(200) DEFAULT NULL, ville_id INT DEFAULT NULL, INDEX IDX_47948BBCA73F0036 (ville_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE depot_ligne (depot_id INT NOT NULL, ligne_id INT NOT NULL, INDEX IDX_310188248510D4DE (depot_id), INDEX IDX_310188245A438E76 (ligne_id), PRIMARY KEY (depot_id, ligne_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE materiel_depot (id INT AUTO_INCREMENT NOT NULL, arrivee DATE DEFAULT NULL, fin DATE DEFAULT NULL, effectif INT DEFAULT NULL, effectif_date DATE DEFAULT NULL, materiel_id INT DEFAULT NULL, depot_id INT DEFAULT NULL, INDEX IDX_9539290816880AAF (materiel_id), INDEX IDX_953929088510D4DE (depot_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE depot ADD CONSTRAINT FK_47948BBCA73F0036 FOREIGN KEY (ville_id) REFERENCES ville (id)');
        $this->addSql('ALTER TABLE depot_ligne ADD CONSTRAINT FK_310188248510D4DE FOREIGN KEY (depot_id) REFERENCES depot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE depot_ligne ADD CONSTRAINT FK_310188245A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE materiel_depot ADD CONSTRAINT FK_9539290816880AAF FOREIGN KEY (materiel_id) REFERENCES materiel (id)');
        $this->addSql('ALTER TABLE materiel_depot ADD CONSTRAINT FK_953929088510D4DE FOREIGN KEY (depot_id) REFERENCES depot (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE materiel_depot DROP FOREIGN KEY FK_9539290816880AAF');
        $this->addSql('ALTER TABLE materiel_depot DROP FOREIGN KEY FK_953929088510D4DE');
        $this->addSql('DROP TABLE materiel_depot');

        $this->addSql('ALTER TABLE depot_ligne DROP FOREIGN KEY FK_310188248510D4DE');
        $this->addSql('ALTER TABLE depot_ligne DROP FOREIGN KEY FK_310188245A438E76');
        $this->addSql('DROP TABLE depot_ligne');

        $this->addSql('ALTER TABLE depot DROP FOREIGN KEY FK_47948BBCA73F0036');
        $this->addSql('DROP TABLE depot');
    }
}
