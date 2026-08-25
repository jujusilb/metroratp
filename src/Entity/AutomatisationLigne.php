<?php

namespace App\Entity;

use App\Repository\AutomatisationLigneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AutomatisationLigneRepository::class)]
class AutomatisationLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Date a laquelle ce palier d'automatisation a ete atteint sur cette Ligne (ex. date de fin de
     * pose des portes palieres, ou date de bascule en conduite totalement automatisee). Une Ligne
     * peut avoir plusieurs AutomatisationLigne au fil du temps, une par palier franchi. Nullable :
     * le palier peut etre connu sans que sa date precise le soit.
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateDeMiseEnPlace = null;

    #[ORM\ManyToOne(inversedBy: 'automatisationLignes')]
    private ?Automatisation $automatisation = null;

    #[ORM\ManyToOne(inversedBy: 'automatisationLignes')]
    private ?Ligne $ligne = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDeMiseEnPlace(): ?\DateTime
    {
        return $this->dateDeMiseEnPlace;
    }

    public function setDateDeMiseEnPlace(?\DateTime $dateDeMiseEnPlace): static
    {
        $this->dateDeMiseEnPlace = $dateDeMiseEnPlace;

        return $this;
    }

    public function getAutomatisation(): ?Automatisation
    {
        return $this->automatisation;
    }

    public function setAutomatisation(?Automatisation $automatisation): static
    {
        $this->automatisation = $automatisation;

        return $this;
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
}
