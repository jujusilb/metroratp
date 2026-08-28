<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute DepotGestionnaire (affectation d'un Depot a un Gestionnaire, avec periode) : un depot
 * physique n'appartient qu'a un seul exploitant a la fois, mais l'exploitant peut changer lors
 * d'un nouvel appel d'offres IDFM - meme raisonnement que DepotLigne/MaterielLigne/MaterielDepot,
 * une entite datee plutot qu'un simple champ gestionnaire_id sur Depot.
 */
final class Version20260828170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute DepotGestionnaire (affectation datee Depot <-> Gestionnaire)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE depot_gestionnaire (id INT AUTO_INCREMENT NOT NULL, arrivee DATE DEFAULT NULL, fin DATE DEFAULT NULL, depot_id INT DEFAULT NULL, gestionnaire_id INT DEFAULT NULL, INDEX IDX_97495DAB8510D4DE (depot_id), INDEX IDX_97495DAB6885AC1B (gestionnaire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE depot_gestionnaire ADD CONSTRAINT FK_97495DAB8510D4DE FOREIGN KEY (depot_id) REFERENCES depot (id)');
        $this->addSql('ALTER TABLE depot_gestionnaire ADD CONSTRAINT FK_97495DAB6885AC1B FOREIGN KEY (gestionnaire_id) REFERENCES gestionnaire (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE depot_gestionnaire DROP FOREIGN KEY FK_97495DAB8510D4DE');
        $this->addSql('ALTER TABLE depot_gestionnaire DROP FOREIGN KEY FK_97495DAB6885AC1B');
        $this->addSql('DROP TABLE depot_gestionnaire');
    }
}
