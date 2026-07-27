<?php

namespace App\Entity;

use App\Repository\MaterielLigneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaterielLigneRepository::class)]
class MaterielLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $arrivee = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $fin = null;

    #[ORM\ManyToOne(inversedBy: 'materielLignes')]
    private ?Materiel $materiel = null;

    #[ORM\ManyToOne(inversedBy: 'materielLignes')]
    private ?Ligne $ligne = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArrivee(): ?\DateTime
    {
        return $this->arrivee;
    }

    public function setArrivee(?\DateTime $arrivee): static
    {
        $this->arrivee = $arrivee;

        return $this;
    }

    public function getFin(): ?\DateTime
    {
        return $this->fin;
    }

    public function setFin(?\DateTime $fin): static
    {
        $this->fin = $fin;

        return $this;
    }

    public function getMateriel(): ?Materiel
    {
        return $this->materiel;
    }

    public function setMateriel(?Materiel $materiel): static
    {
        $this->materiel = $materiel;

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
