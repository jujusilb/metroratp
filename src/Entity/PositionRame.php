<?php

namespace App\Entity;

use App\Repository\PositionRameRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conseil de positionnement dans la rame (dataset IDFM "positionnement-dans-la-rame") : sur une
 * Ligne, a une Station donnee, ou se placer (Avant/Milieu/Arriere + position/positionMax) pour
 * arriver au plus pres d'une sortie (Acces) ou d'un point de correspondance a l'arrivee.
 */
#[ORM\Entity(repositoryClass: PositionRameRepository::class)]
class PositionRame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ligne $ligne = null;

    /**
     * La Station ou ce conseil s'applique (cote depart : c'est en montant ici, sur cette Ligne,
     * qu'il faut se positionner).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Station $station = null;

    /**
     * Libelle du point vise (nom de la station de correspondance, ou nom/adresse de la sortie) -
     * texte source IDFM, pas toujours resoluble vers une entite (voir acces).
     */
    #[ORM\Column(length: 150)]
    private string $destination;

    /**
     * Renseigne uniquement quand le point vise est une sortie identifiee (Acces) plutot qu'une
     * simple correspondance vers une autre ligne.
     */
    #[ORM\ManyToOne]
    private ?Acces $acces = null;

    #[ORM\Column(length: 20)]
    private string $labelPosition;

    #[ORM\Column]
    private int $position;

    #[ORM\Column]
    private int $positionMax;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $equipement = null;

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

    public function getStation(): ?Station
    {
        return $this->station;
    }

    public function setStation(?Station $station): static
    {
        $this->station = $station;

        return $this;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getAcces(): ?Acces
    {
        return $this->acces;
    }

    public function setAcces(?Acces $acces): static
    {
        $this->acces = $acces;

        return $this;
    }

    public function getLabelPosition(): string
    {
        return $this->labelPosition;
    }

    public function setLabelPosition(string $labelPosition): static
    {
        $this->labelPosition = $labelPosition;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPositionMax(): int
    {
        return $this->positionMax;
    }

    public function setPositionMax(int $positionMax): static
    {
        $this->positionMax = $positionMax;

        return $this;
    }

    public function getEquipement(): ?string
    {
        return $this->equipement;
    }

    public function setEquipement(?string $equipement): static
    {
        $this->equipement = $equipement;

        return $this;
    }
}
