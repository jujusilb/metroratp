<?php

namespace App\Tests\Entity;

use App\Entity\Mission;
use App\Entity\Service;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use PHPUnit\Framework\TestCase;

/**
 * Teste les champs calcules de Mission (depart/arrivee/troncon), issus du redesign qui a
 * remplace Mission::troncon/sens par Mission::tronconDesserte/direction.
 */
final class MissionTest extends TestCase
{
    use GraphFixtureTrait;

    public function testGetDepartEtArriveeSeDeduisentDuTronconDesserte(): void
    {
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('Châtelet'));
        $b = $this->createDesserte($ligne, $this->createStation('Bastille'));
        $troncon = $this->createTroncon();
        $this->linkTroncon($troncon, $a, $b);

        // Retrouve la TronconDesserte "Depart" (= $a) fraichement liee au troncon.
        $tronconDesserteDepart = null;
        foreach ($troncon->getTronconDessertes() as $tronconDesserte) {
            if ('Départ' === $tronconDesserte->getTypeDesserte()?->getLabel()) {
                $tronconDesserteDepart = $tronconDesserte;
            }
        }
        self::assertNotNull($tronconDesserteDepart);

        $service = new Service();
        $service->setLabel('Unique');

        $mission = new Mission();
        $mission->setNumero(1);
        $mission->setService($service);
        $mission->setTronconDesserte($tronconDesserteDepart);
        $mission->setDirection($b);

        self::assertSame($troncon, $mission->getTroncon());
        self::assertSame($a, $mission->getDepart());
        self::assertSame($b, $mission->getArrivee());
    }

    public function testGetArriveeEstNullSiAucuneAutreDesserteNeJoueLeRoleArrivee(): void
    {
        // Troncon avec seulement une "Depart" (situation anormale), l'arrivee doit rester null
        // plutot que de planter.
        $troncon = $this->createTroncon();
        $ligne = $this->createLigne('1');
        $a = $this->createDesserte($ligne, $this->createStation('Châtelet'));

        $departType = new TypeDesserte();
        $departType->setLabel('Départ');

        $tronconDesserte = new TronconDesserte();
        $tronconDesserte->setTroncon($troncon);
        $tronconDesserte->setDesserte($a);
        $tronconDesserte->setTypeDesserte($departType);
        $this->addToInverseCollection($troncon, 'tronconDessertes', $tronconDesserte);

        $service = new Service();
        $service->setLabel('Unique');

        $mission = new Mission();
        $mission->setNumero(1);
        $mission->setService($service);
        $mission->setTronconDesserte($tronconDesserte);
        $mission->setDirection($a);

        self::assertNull($mission->getArrivee());
    }
}
