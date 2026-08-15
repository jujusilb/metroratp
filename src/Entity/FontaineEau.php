<?php

namespace App\Entity;

use App\Repository\FontaineEauRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fontaine a eau en station (dataset IDFM "fontaines-a-eau-dans-le-reseau-ratp", 91 emplacements
 * avec coordonnees exploitables sur 93). Aucune cle stable par ligne dans le CSV source : purge +
 * reimport complet a chaque execution (comme Sanitaire/Defibrillateur).
 *
 * Contrairement a Sanitaire/Defibrillateur/PointDeVente, ce dataset fournit "id IDM de l'acces le
 * plus proche" qui correspond exactement a Acces::codeExterne (verifie a l'import : 91/91
 * rattaches) : le rattachement a Acces (et, par ses Sortie, a Station) est donc OFFICIEL, pas une
 * approximation par proximite geographique.
 */
#[ORM\Entity(repositoryClass: FontaineEauRepository::class)]
class FontaineEau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $ligneLabel = null;

    #[ORM\Column(length: 150)]
    private ?string $label = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $commune = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $numeroAccesProche = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nomAccesProche = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $enZoneControlee = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $identifiantRatp = null;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    /**
     * Rattachement officiel (voir docblock de la classe), pas une approximation.
     */
    #[ORM\ManyToOne]
    private ?Acces $acces = null;

    /**
     * Derive de acces->sorties a l'import, pour affichage direct sur la fiche Station sans
     * naviguer par Acces.
     */
    #[ORM\ManyToOne]
    private ?Station $station = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLigneLabel(): ?string
    {
        return $this->ligneLabel;
    }

    public function setLigneLabel(?string $ligneLabel): static
    {
        $this->ligneLabel = $ligneLabel;

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

    public function getCommune(): ?string
    {
        return $this->commune;
    }

    public function setCommune(?string $commune): static
    {
        $this->commune = $commune;

        return $this;
    }

    public function getNumeroAccesProche(): ?string
    {
        return $this->numeroAccesProche;
    }

    public function setNumeroAccesProche(?string $numeroAccesProche): static
    {
        $this->numeroAccesProche = $numeroAccesProche;

        return $this;
    }

    public function getNomAccesProche(): ?string
    {
        return $this->nomAccesProche;
    }

    public function setNomAccesProche(?string $nomAccesProche): static
    {
        $this->nomAccesProche = $nomAccesProche;

        return $this;
    }

    public function getEnZoneControlee(): ?string
    {
        return $this->enZoneControlee;
    }

    public function setEnZoneControlee(?string $enZoneControlee): static
    {
        $this->enZoneControlee = $enZoneControlee;

        return $this;
    }

    public function getIdentifiantRatp(): ?string
    {
        return $this->identifiantRatp;
    }

    public function setIdentifiantRatp(?string $identifiantRatp): static
    {
        $this->identifiantRatp = $identifiantRatp;

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

    public function getAcces(): ?Acces
    {
        return $this->acces;
    }

    public function setAcces(?Acces $acces): static
    {
        $this->acces = $acces;

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
