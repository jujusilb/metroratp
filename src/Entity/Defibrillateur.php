<?php

namespace App\Entity;

use App\Repository\DefibrillateurRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Defibrillateur en station (dataset IDFM "defibrillateurs-du-reseau-ratp", 451 emplacements).
 * Aucune cle stable dans le CSV source : reconstruit entierement a chaque import (purge +
 * reimport, comme Sanitaire/ProjetArret). Rattachement a Station par proximite geographique (voir
 * app:importer-defibrillateurs), le dataset ne fournissant pas d'identifiant de Station officiel.
 */
#[ORM\Entity(repositoryClass: DefibrillateurRepository::class)]
class Defibrillateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Description brute de l'emplacement telle que fournie par IDFM (ex: "RER A TORCY"),
     * combine souvent ligne+station sans structure exploitable separement.
     */
    #[ORM\Column(length: 150)]
    private ?string $localisation = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $acces = null;

    #[ORM\Column(nullable: true)]
    private ?bool $accesLibre = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $complementLocalisation = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $disponibiliteSemaine = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $disponibiliteHoraires = null;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    /**
     * Station la plus proche (a vol d'oiseau) - rattachement approximatif, pas de cle officielle
     * dans la donnee source.
     */
    #[ORM\ManyToOne]
    private ?Station $station = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): static
    {
        $this->localisation = $localisation;

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

    public function getAcces(): ?string
    {
        return $this->acces;
    }

    public function setAcces(?string $acces): static
    {
        $this->acces = $acces;

        return $this;
    }

    public function isAccesLibre(): ?bool
    {
        return $this->accesLibre;
    }

    public function setAccesLibre(?bool $accesLibre): static
    {
        $this->accesLibre = $accesLibre;

        return $this;
    }

    public function getComplementLocalisation(): ?string
    {
        return $this->complementLocalisation;
    }

    public function setComplementLocalisation(?string $complementLocalisation): static
    {
        $this->complementLocalisation = $complementLocalisation;

        return $this;
    }

    public function getDisponibiliteSemaine(): ?string
    {
        return $this->disponibiliteSemaine;
    }

    public function setDisponibiliteSemaine(?string $disponibiliteSemaine): static
    {
        $this->disponibiliteSemaine = $disponibiliteSemaine;

        return $this;
    }

    public function getDisponibiliteHoraires(): ?string
    {
        return $this->disponibiliteHoraires;
    }

    public function setDisponibiliteHoraires(?string $disponibiliteHoraires): static
    {
        $this->disponibiliteHoraires = $disponibiliteHoraires;

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
