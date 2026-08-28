<?php

namespace App\Entity;

use App\Repository\HoraireLigneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Plage horaire de service d'une Ligne pour un type de jour donne (decoupage classique RATP :
 * Semaine/Samedi/DimancheFerie - les jours feries suivent le regime "DimancheFerie" mais ne sont
 * pas calcules automatiquement, cf. limite assumee dans extraire_horaires_lignes.php). Une Ligne
 * peut avoir 0 a 3 HoraireLigne (ex: un Noctilien n'a qu'une entree Samedi/DimancheFerie).
 *
 * dernierDepart < premierDepart signale une plage qui franchit minuit (ex: 04:41 -> 02:16) : voir
 * TrajetFinder::estEnService(). Peuplee via documentation/COOK/scripts/extraire_horaires_lignes.php
 * + app:importer-horaires-lignes (premier/dernier depart observes dans le GTFS IDFM, PAS une
 * donnee officielle RATP - une simple estimation, comme le reste des temps du calculateur).
 */
#[ORM\Entity(repositoryClass: HoraireLigneRepository::class)]
#[ORM\UniqueConstraint(name: 'horaire_ligne_unique_type_jour', columns: ['ligne_id', 'type_jour'])]
class HoraireLigne
{
    public const TYPES_JOUR = ['Semaine', 'Samedi', 'DimancheFerie'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'horaireLignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ligne $ligne = null;

    #[Assert\Choice(choices: self::TYPES_JOUR)]
    #[ORM\Column(length: 20)]
    private ?string $typeJour = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $premierDepart = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $dernierDepart = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLigne(): ?Ligne
    {
        return $this->ligne;
    }

    public function setLigne(?Ligne $ligne): static
    {
        $this->ligne = $ligne;

        return $this;
    }

    public function getTypeJour(): ?string
    {
        return $this->typeJour;
    }

    public function setTypeJour(?string $typeJour): static
    {
        $this->typeJour = $typeJour;

        return $this;
    }

    public function getPremierDepart(): ?\DateTime
    {
        return $this->premierDepart;
    }

    public function setPremierDepart(?\DateTime $premierDepart): static
    {
        $this->premierDepart = $premierDepart;

        return $this;
    }

    public function getDernierDepart(): ?\DateTime
    {
        return $this->dernierDepart;
    }

    public function setDernierDepart(?\DateTime $dernierDepart): static
    {
        $this->dernierDepart = $dernierDepart;

        return $this;
    }
}
