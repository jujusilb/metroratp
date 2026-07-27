<?php

namespace App\Tests\Entity;

use PHPUnit\Framework\TestCase;

/**
 * Teste Troncon::getDesserteForRole, le coeur du calcul de "l'autre bout" d'un troncon
 * (utilise par Mission::getArrivee et par Ligne::getParcoursSegments pour parcourir le graphe).
 */
final class TronconTest extends TestCase
{
    use GraphFixtureTrait;

    public function testGetDesserteForRoleTrouveLaDesserteCorrespondante(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $troncon = $this->createTroncon();
        $this->linkTroncon($troncon, $a, $b);

        self::assertSame($b, $troncon->getDesserteForRole('Arrivée'));
        self::assertSame($a, $troncon->getDesserteForRole('Départ'));
    }

    public function testGetDesserteForRoleAvecExclusionIgnoreLaDesserteExclue(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $troncon = $this->createTroncon();
        $this->linkTroncon($troncon, $a, $b);

        // En excluant $b elle-meme, il ne doit plus rien trouver pour le role "Arrivée".
        self::assertNull($troncon->getDesserteForRole('Arrivée', $b));
    }

    public function testGetDesserteForRoleRenvoieNullSiAucunRoleNeCorrespond(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $troncon = $this->createTroncon();
        $this->linkTroncon($troncon, $a, $b);

        self::assertNull($troncon->getDesserteForRole('Role inexistant'));
    }
}
