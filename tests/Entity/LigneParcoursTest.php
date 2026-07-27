<?php

namespace App\Tests\Entity;

use PHPUnit\Framework\TestCase;

/**
 * Teste la reconstruction du parcours d'une ligne (Ligne::getParcoursSegments) et le calcul
 * des terminus, en pur PHP (pas de base de donnees), pour verifier le comportement sur une
 * ligne lineaire et sur une ligne avec embranchement (ex: ligne 7).
 */
final class LigneParcoursTest extends TestCase
{
    use GraphFixtureTrait;

    public function testLigneLineaireProduitUnSeulSegmentDansLOrdre(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $c = $this->createDesserte($ligne, $this->createStation('C'));

        $this->linkTroncon($this->createTroncon(), $a, $b);
        $this->linkTroncon($this->createTroncon(), $b, $c);

        $segments = $ligne->getParcoursSegments();

        self::assertCount(1, $segments);
        self::assertSame(['A', 'B', 'C'], array_column($segments[0]['stations'], 'label'));
        self::assertSame([false, false, false], array_column($segments[0]['stations'], 'rejoint'));
        self::assertSame([], $segments[0]['branches']);
    }

    public function testLigneAvecEmbranchementProduitDeuxBranches(): void
    {
        // Trajet: A -> B -> (C ou D), comme la ligne 7 apres Maison Blanche.
        $ligne = $this->createLigne('7');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $c = $this->createDesserte($ligne, $this->createStation('C'));
        $d = $this->createDesserte($ligne, $this->createStation('D'));

        $this->linkTroncon($this->createTroncon(), $a, $b);
        $this->linkTroncon($this->createTroncon(), $b, $c);
        $this->linkTroncon($this->createTroncon(), $b, $d);

        $segments = $ligne->getParcoursSegments();

        self::assertCount(1, $segments);
        self::assertSame(['A', 'B'], array_column($segments[0]['stations'], 'label'));

        $branches = $segments[0]['branches'];
        self::assertCount(2, $branches);

        $branchLabels = array_map(
            static fn (array $branch): array => array_column($branch['stations'], 'label'),
            $branches
        );
        self::assertContainsEquals(['C'], $branchLabels);
        self::assertContainsEquals(['D'], $branchLabels);
    }

    public function testLigneAvecFusionMarqueRejointSansRepeterLaSuite(): void
    {
        // Deux branches (B et C) fusionnent vers D, comme la ligne 13 vers La Fourche.
        $ligne = $this->createLigne('13');
        $racine1 = $this->createDesserte($ligne, $this->createStation('B'));
        $racine2 = $this->createDesserte($ligne, $this->createStation('C'));
        $d = $this->createDesserte($ligne, $this->createStation('D'));
        $e = $this->createDesserte($ligne, $this->createStation('E'));

        $this->linkTroncon($this->createTroncon(), $racine1, $d);
        $this->linkTroncon($this->createTroncon(), $racine2, $d);
        $this->linkTroncon($this->createTroncon(), $d, $e);

        $segments = $ligne->getParcoursSegments();

        // Un seul terminus racine est retenu (le premier trouve) : un seul segment de depart.
        self::assertCount(1, $segments);

        $stations = $segments[0]['stations'];
        $labels = array_column($stations, 'label');

        self::assertContains('D', $labels);
        self::assertContains('E', $labels);
        // "D" ne doit apparaitre qu'une seule fois avec rejoint=false (pas de duplication de "E").
        $dOccurrences = array_filter($stations, static fn (array $s): bool => 'D' === $s['label']);
        self::assertCount(1, $dOccurrences);
    }

    public function testGetTerminusLabelsNeGardeQueLesDessertesAUnSeulTroncon(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('A'));
        $b = $this->createDesserte($ligne, $this->createStation('B'));
        $c = $this->createDesserte($ligne, $this->createStation('C'));

        $this->linkTroncon($this->createTroncon(), $a, $b);
        $this->linkTroncon($this->createTroncon(), $b, $c);

        self::assertSame(['A', 'C'], $ligne->getTerminusLabels());
    }

    public function testGetNombreStations(): void
    {
        $ligne = $this->createLigne('1');
        $this->createDesserte($ligne, $this->createStation('A'));
        $this->createDesserte($ligne, $this->createStation('B'));

        self::assertSame(2, $ligne->getNombreStations());
    }
}
