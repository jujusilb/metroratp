<?php

namespace App\Entity;

use App\Repository\SanitaireRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Toilette publique en station (dataset IDFM "sanitaires-reseau-ratp", 60 emplacements). Aucune
 * cle stable dans le CSV source : reconstruit entierement a chaque import (purge + reimport,
 * comme ProjetArret). Rattachement a Station par proximite geographique (voir
 * app:importer-sanitaires), le dataset ne fournissant pas d'identifiant de Station officiel.
 */
#[ORM\Entity(repositoryClass: SanitaireRepository::class)]
class Sanitaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $ligneLabel = null;

    #[ORM\Column(length: 150)]
    private ?string $label = null;

    #[ORM\Column(nullable: true)]
    private ?bool $accessiblePublic = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tarif = null;

    #[ORM\Column(nullable: true)]
    private ?bool $accesPassNavigoTicketT = null;

    #[ORM\Column(nullable: true)]
    private ?bool $accesBoutonPoussoir = null;

    #[ORM\Column(nullable: true)]
    private ?bool $enZoneControlee = null;

    #[ORM\Column(nullable: true)]
    private ?bool $horsZoneControleeStation = null;

    #[ORM\Column(nullable: true)]
    private ?bool $horsZoneControleeVoiePublique = null;

    #[ORM\Column(nullable: true)]
    private ?bool $accessibilitePmr = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $localisation = null;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gestionnaire = null;

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

    public function isAccessiblePublic(): ?bool
    {
        return $this->accessiblePublic;
    }

    public function setAccessiblePublic(?bool $accessiblePublic): static
    {
        $this->accessiblePublic = $accessiblePublic;

        return $this;
    }

    public function getTarif(): ?string
    {
        return $this->tarif;
    }

    public function setTarif(?string $tarif): static
    {
        $this->tarif = $tarif;

        return $this;
    }

    public function isAccesPassNavigoTicketT(): ?bool
    {
        return $this->accesPassNavigoTicketT;
    }

    public function setAccesPassNavigoTicketT(?bool $accesPassNavigoTicketT): static
    {
        $this->accesPassNavigoTicketT = $accesPassNavigoTicketT;

        return $this;
    }

    public function isAccesBoutonPoussoir(): ?bool
    {
        return $this->accesBoutonPoussoir;
    }

    public function setAccesBoutonPoussoir(?bool $accesBoutonPoussoir): static
    {
        $this->accesBoutonPoussoir = $accesBoutonPoussoir;

        return $this;
    }

    public function isEnZoneControlee(): ?bool
    {
        return $this->enZoneControlee;
    }

    public function setEnZoneControlee(?bool $enZoneControlee): static
    {
        $this->enZoneControlee = $enZoneControlee;

        return $this;
    }

    public function isHorsZoneControleeStation(): ?bool
    {
        return $this->horsZoneControleeStation;
    }

    public function setHorsZoneControleeStation(?bool $horsZoneControleeStation): static
    {
        $this->horsZoneControleeStation = $horsZoneControleeStation;

        return $this;
    }

    public function isHorsZoneControleeVoiePublique(): ?bool
    {
        return $this->horsZoneControleeVoiePublique;
    }

    public function setHorsZoneControleeVoiePublique(?bool $horsZoneControleeVoiePublique): static
    {
        $this->horsZoneControleeVoiePublique = $horsZoneControleeVoiePublique;

        return $this;
    }

    public function isAccessibilitePmr(): ?bool
    {
        return $this->accessibilitePmr;
    }

    public function setAccessibilitePmr(?bool $accessibilitePmr): static
    {
        $this->accessibilitePmr = $accessibilitePmr;

        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): static
    {
        $this->localisation = $localisation;

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

    public function getGestionnaire(): ?string
    {
        return $this->gestionnaire;
    }

    public function setGestionnaire(?string $gestionnaire): static
    {
        $this->gestionnaire = $gestionnaire;

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
