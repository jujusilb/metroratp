<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Transforme depot_ligne d'un simple ManyToMany (depot_id, ligne_id) en une vraie entite datee
 * (DepotLigne : id, arrivee, fin) - une Ligne de bus peut changer de depot d'affectation au fil du
 * temps (reorganisation, electrification progressive), un simple ManyToMany ne peut pas porter de
 * date. Aucune donnee perdue : la table venait d'etre creee (Version20260828150000), toujours vide.
 */
final class Version20260828160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transforme depot_ligne en entite datee DepotLigne (id, arrivee, fin)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE depot_ligne DROP FOREIGN KEY FK_310188245A438E76');
        $this->addSql('ALTER TABLE depot_ligne DROP FOREIGN KEY FK_310188248510D4DE');
        $this->addSql('ALTER TABLE depot_ligne ADD id INT AUTO_INCREMENT NOT NULL, ADD arrivee DATE DEFAULT NULL, ADD fin DATE DEFAULT NULL, CHANGE depot_id depot_id INT DEFAULT NULL, CHANGE ligne_id ligne_id INT DEFAULT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE depot_ligne ADD CONSTRAINT FK_310188245A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
        $this->addSql('ALTER TABLE depot_ligne ADD CONSTRAINT FK_310188248510D4DE FOREIGN KEY (depot_id) REFERENCES depot (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE depot_ligne DROP FOREIGN KEY FK_310188245A438E76');
        $this->addSql('ALTER TABLE depot_ligne DROP FOREIGN KEY FK_310188248510D4DE');
        $this->addSql('ALTER TABLE depot_ligne DROP PRIMARY KEY, DROP id, DROP arrivee, DROP fin, CHANGE depot_id depot_id INT NOT NULL, CHANGE ligne_id ligne_id INT NOT NULL, ADD PRIMARY KEY (depot_id, ligne_id)');
        $this->addSql('ALTER TABLE depot_ligne ADD CONSTRAINT FK_310188245A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE depot_ligne ADD CONSTRAINT FK_310188248510D4DE FOREIGN KEY (depot_id) REFERENCES depot (id) ON DELETE CASCADE');
    }
}
