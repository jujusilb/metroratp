<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remplace Automatisation/AutomatisationLigne (une seule date par Ligne, ne pouvait pas
 * representer un deploiement partiel de portes palieres) par deux champs simples, sur suggestion
 * de l'utilisateur : Ligne.dateAutomatisationTotale (conduite sans conducteur, propriete de toute
 * la ligne) et Desserte.datePortePaliere (installation quai par quai, peut rester partielle -
 * ex. Ligne 13). Voir documentation/TODO.md.
 */
final class Version20260825221456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace Automatisation/AutomatisationLigne par Ligne.dateAutomatisationTotale et Desserte.datePortePaliere';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE automatisation_ligne DROP FOREIGN KEY FK_B69AFD005A438E76');
        $this->addSql('ALTER TABLE automatisation_ligne DROP FOREIGN KEY FK_B69AFD00ECF39A72');
        $this->addSql('DROP TABLE automatisation_ligne');
        $this->addSql('DROP TABLE automatisation');
        $this->addSql('ALTER TABLE ligne ADD date_automatisation_totale DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE desserte ADD date_porte_paliere DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne DROP date_automatisation_totale');
        $this->addSql('ALTER TABLE desserte DROP date_porte_paliere');
        $this->addSql('CREATE TABLE automatisation (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE automatisation_ligne (id INT AUTO_INCREMENT NOT NULL, date_de_mise_en_place DATE DEFAULT NULL, automatisation_id INT DEFAULT NULL, ligne_id INT DEFAULT NULL, INDEX IDX_B69AFD00ECF39A72 (automatisation_id), INDEX IDX_B69AFD005A438E76 (ligne_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE automatisation_ligne ADD CONSTRAINT FK_B69AFD00ECF39A72 FOREIGN KEY (automatisation_id) REFERENCES automatisation (id)');
        $this->addSql('ALTER TABLE automatisation_ligne ADD CONSTRAINT FK_B69AFD005A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
    }
}
