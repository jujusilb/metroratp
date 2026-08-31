<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrige Ligne.couleur : 1443 des ~1459 Ligne (RER + quasi tout le reseau bus, importees via
 * app:importer-reseau-complet/app:importer-lignes-rer depuis referentiel-des-lignes.csv, champ
 * ColourWeb_hexa deja sans "#" a la source) ont une couleur hexadecimale SANS le "#" initial -
 * trouve en verifiant l'affichage de templates/pole_echange/show.html.twig (les pastilles de
 * couleur des lignes de metro fonctionnaient, celles du RER/bus non : une valeur CSS invalide dans
 * une custom property ne retombe pas sur le |default('#6c757d') du template, contrairement a une
 * valeur simplement absente). Toutes les ~30 utilisations de Ligne.couleur dans templates/ l'ecrivent
 * directement sans jamais concatener leur propre "#" - corriger la donnee est donc sans risque de
 * double "##" nulle part.
 */
final class Version20260831190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le # manquant sur Ligne.couleur (RER + quasi tout le bus, valeur CSS invalide sans lui)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE ligne SET couleur = CONCAT('#', couleur) WHERE couleur IS NOT NULL AND couleur NOT LIKE '#%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE ligne SET couleur = SUBSTRING(couleur, 2) WHERE couleur LIKE '#%'");
    }
}
