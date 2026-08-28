<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la correspondance Opera (M7, desserte 169) <-> Auber (RER A, desserte 411) : les deux
 * stations partagent le pole d'echange "Paris Saint-Lazare - Opera" mais aucune correspondance
 * ne les reliait directement (seul transferts.txt/GTFS avait ete importe, et ne couvrait pas ce
 * passage souterrain), si bien que TrajetFinder ne proposait jamais ce changement pourtant reel
 * (couloir avec tapis roulant, sans repasser de controle d'acces). Distance laissee NULL : pas
 * de valeur officielle connue, cf. Correspondance::$distance.
 */
final class Version20260828190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la correspondance directe Opera (M7) <-> Auber (RER A)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO correspondance (desserte_a_id, desserte_b_id, distance, in_zone) VALUES (169, 411, NULL, 1)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM correspondance WHERE desserte_a_id = 169 AND desserte_b_id = 411 AND direction_a_id IS NULL AND direction_b_id IS NULL');
    }
}
