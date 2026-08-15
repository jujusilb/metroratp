<?php

namespace App\Entity;

use App\Repository\PointDeVenteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Point de vente de titres de transport (dataset IDFM "points-de-vente" : commerces de proximite
 * agrees et guichets Navigo, 2012 points). Le dataset source ne donne aucune cle de rattachement
 * fiable vers une Station (ZdAId toujours a 0 dans l'export) : rattachement fait par proximite
 * geographique a l'import (voir app:importer-points-de-vente), pas par un identifiant officiel -
 * Station::pointsDeVente doit donc etre lu comme "les plus proches", pas "les points de vente
 * officiels de cette station precise".
 */
#[ORM\Entity(repositoryClass: PointDeVenteRepository::class)]
class PointDeVente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $codeExterne = null;

    #[ORM\Column(length: 150)]
    private ?string $label = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $horaires = null;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    /**
     * Station la plus proche (a vol d'oiseau, dans un rayon raisonnable) - repli approximatif en
     * l'absence de rattachement officiel dans la donnee source (voir docblock de la classe).
     */
    #[ORM\ManyToOne]
    private ?Station $station = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeExterne(): ?string
    {
        return $this->codeExterne;
    }

    public function setCodeExterne(string $codeExterne): static
    {
        $this->codeExterne = $codeExterne;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getHoraires(): ?string
    {
        return $this->horaires;
    }

    public function setHoraires(?string $horaires): static
    {
        $this->horaires = $horaires;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

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
}
