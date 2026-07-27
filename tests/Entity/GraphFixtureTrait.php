<?php

namespace App\Tests\Entity;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Aide a construire un petit graphe troncon/desserte en memoire, sans base de donnees,
 * pour tester la logique pure de parcours (Ligne::getParcoursSegments, etc).
 *
 * TronconDesserte ne synchronise que le cote proprietaire (setTroncon/setDesserte) : les
 * collections inverses Troncon::tronconDessertes et Desserte::tronconDessertes ne sont donc
 * jamais remplies automatiquement en dehors de Doctrine (qui les charge depuis la base). On
 * les alimente ici via Reflection, ce qui reproduit fidelement l'etat qu'aurait un objet
 * charge depuis la base.
 */
trait GraphFixtureTrait
{
    private static int $nextId = 1;

    private function setEntityId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function createStation(string $label): Station
    {
        $station = new Station();
        $station->setLabel($label);
        $this->setEntityId($station, self::$nextId++);

        return $station;
    }

    private function createLigne(string $label = '1'): Ligne
    {
        $ligne = new Ligne();
        $ligne->setLabel($label);
        $this->setEntityId($ligne, self::$nextId++);

        return $ligne;
    }

    private function createDesserte(Ligne $ligne, Station $station): Desserte
    {
        $desserte = new Desserte();
        $desserte->setLigne($ligne);
        $desserte->setStation($station);
        $this->setEntityId($desserte, self::$nextId++);
        $ligne->addDesserte($desserte);
        $station->addDesserte($desserte);

        return $desserte;
    }

    private function createTroncon(): Troncon
    {
        $troncon = new Troncon();
        $this->setEntityId($troncon, self::$nextId++);

        return $troncon;
    }

    /**
     * Relie un troncon a deux dessertes (depart -> arrivee), en peuplant a la fois les cotes
     * proprietaires (TronconDesserte) et les collections inverses (Troncon/Desserte).
     */
    private function linkTroncon(Troncon $troncon, Desserte $depart, Desserte $arrivee): void
    {
        $departType = new TypeDesserte();
        $departType->setLabel('Départ');

        $arriveeType = new TypeDesserte();
        $arriveeType->setLabel('Arrivée');

        $tdDepart = new TronconDesserte();
        $tdDepart->setTroncon($troncon);
        $tdDepart->setDesserte($depart);
        $tdDepart->setTypeDesserte($departType);

        $tdArrivee = new TronconDesserte();
        $tdArrivee->setTroncon($troncon);
        $tdArrivee->setDesserte($arrivee);
        $tdArrivee->setTypeDesserte($arriveeType);

        $this->addToInverseCollection($troncon, 'tronconDessertes', $tdDepart);
        $this->addToInverseCollection($troncon, 'tronconDessertes', $tdArrivee);
        $this->addToInverseCollection($depart, 'tronconDessertes', $tdDepart);
        $this->addToInverseCollection($arrivee, 'tronconDessertes', $tdArrivee);
    }

    private function addToInverseCollection(object $entity, string $property, object $value): void
    {
        $reflectionProperty = new \ReflectionProperty($entity, $property);
        $reflectionProperty->setAccessible(true);
        /** @var ArrayCollection<int, object> $collection */
        $collection = $reflectionProperty->getValue($entity);
        $collection->add($value);
    }
}
