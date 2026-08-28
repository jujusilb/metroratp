<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute HoraireLigne : plage horaire de service par Ligne et par type de jour (Semaine/Samedi/
 * DimancheFerie), pour que le calculateur de trajet puisse exclure une ligne fermee au moment
 * demande (ex: Noctilien propose en pleine journee, ou l'inverse - remarque utilisateur).
 * Peuplee ensuite via app:importer-horaires-lignes.
 */
final class Version20260828200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table horaire_ligne (plage horaire de service par Ligne et type de jour)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE horaire_ligne (id INT AUTO_INCREMENT NOT NULL, type_jour VARCHAR(20) NOT NULL, premier_depart TIME DEFAULT NULL, dernier_depart TIME DEFAULT NULL, ligne_id INT NOT NULL, INDEX IDX_32186C585A438E76 (ligne_id), UNIQUE INDEX horaire_ligne_unique_type_jour (ligne_id, type_jour), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE horaire_ligne ADD CONSTRAINT FK_32186C585A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE horaire_ligne');
    }
}
