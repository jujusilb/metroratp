<?php

namespace App\Entity;

use App\Repository\SanisettePubliqueRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Toilette publique de la Ville de Paris (dataset Paris Open Data "sanisettesparis2011", 609
 * emplacements), distincte des Sanitaire RATP en station (autre gestionnaire, autre dataset).
 * Aucune cle stable dans le CSV source : purge + reimport complet a chaque execution (comme
 * Sanitaire/Defibrillateur/FontaineEau). Rattachement a Station par proximite geographique (le
 * dataset ne fournit aucun identifiant de reseau, seulement une adresse et des coordonnees) :
 * 606/609 (99%) rattachees a moins de 300m - Paris intra-muros est dense en arrets de bus, donc
 * la plupart des sanisettes de voirie se trouvent malgre tout pres d'un arret du reseau.
 *
 * `source`/`complement_adresse` du CSV source sont identiques sur les 609 lignes (constants,
 * aucune vraie donnee) : non importes, meme decision que pour `agency.txt`.
 */
#[ORM\Entity(repositoryClass: SanisettePubliqueRepository::class)]
class SanisettePublique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $arrondissement = null;

    #[ORM\Column(length: 30)]
    private ?string $type = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column(length: 150)]
    private ?string $adresse = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $horaire = null;

    #[ORM\Column(nullable: true)]
    private ?bool $accesPmr = null;

    #[ORM\Column(nullable: true)]
    private ?bool $relaisBebe = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $urlFicheEquipement = null;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gestionnaire = null;

    /**
     * Station la plus proche (a vol d'oiseau) - rattachement approximatif, pas de cle officielle
     * dans la donnee source. La plupart des sanisettes n'ont aucune Station a proximite (dataset
     * de voirie parisienne, pas specifique au reseau de transport).
     */
    #[ORM\ManyToOne]
    private ?Station $station = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArrondissement(): ?string
    {
        return $this->arrondissement;
    }

    public function setArrondissement(?string $arrondissement): static
    {
        $this->arrondissement = $arrondissement;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getHoraire(): ?string
    {
        return $this->horaire;
    }

    public function setHoraire(?string $horaire): static
    {
        $this->horaire = $horaire;

        return $this;
    }

    public function isAccesPmr(): ?bool
    {
        return $this->accesPmr;
    }

    public function setAccesPmr(?bool $accesPmr): static
    {
        $this->accesPmr = $accesPmr;

        return $this;
    }

    public function isRelaisBebe(): ?bool
    {
        return $this->relaisBebe;
    }

    public function setRelaisBebe(?bool $relaisBebe): static
    {
        $this->relaisBebe = $relaisBebe;

        return $this;
    }

    public function getUrlFicheEquipement(): ?string
    {
        return $this->urlFicheEquipement;
    }

    public function setUrlFicheEquipement(?string $urlFicheEquipement): static
    {
        $this->urlFicheEquipement = $urlFicheEquipement;

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
