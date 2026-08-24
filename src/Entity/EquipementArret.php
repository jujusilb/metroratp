<?php

namespace App\Entity;

use App\Repository\EquipementArretRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Equipements d'un arret physique (niveau ArT du referentiel IDFM - un quai/poteau donne, pas
 * toute la Station) releves par croisement avec OpenStreetMap (dataset IDFM
 * "ecarts-arrets-referentiel-et-openstreetmap" - voir app:importer-equipements-arrets). Rattache a
 * Station via relations.csv (ArTId -> ZdCId -> Station.codeExterne), PAS par proximite : cle
 * stable artId, reimportable sans casser les references.
 *
 * Les booleens sont a null quand OpenStreetMap n'a pas ce tag ou a une valeur ambigue (ex.
 * "limited", ou plusieurs elements OSM distincts avec des tags contradictoires pour le meme
 * ArT) - pas une absence d'equipement, une absence d'information.
 */
#[ORM\Entity(repositoryClass: EquipementArretRepository::class)]
class EquipementArret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * ArTId du referentiel IDFM (identifiant stable de l'arret physique) - cle d'import, pas de
     * signification metier au dela de reimporter sans doublons.
     */
    #[ORM\Column(unique: true)]
    private ?int $artId = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    /**
     * Commune reelle (referentiel geo.api.gouv.fr, voir Ville), rattachee par correspondance de
     * nom depuis le champ ville ci-dessus - voir app:importer-villes.
     */
    #[ORM\ManyToOne]
    private ?Ville $villeRef = null;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    #[ORM\Column(nullable: true)]
    private ?bool $accessibleFauteuilRoulant = null;

    #[ORM\Column(nullable: true)]
    private ?bool $banc = null;

    #[ORM\Column(nullable: true)]
    private ?bool $poubelle = null;

    #[ORM\Column(nullable: true)]
    private ?bool $eclairage = null;

    #[ORM\Column(nullable: true)]
    private ?bool $abri = null;

    #[ORM\Column(nullable: true)]
    private ?bool $bandeTactile = null;

    /**
     * Distance (m) entre la position OpenStreetMap et la position officielle du referentiel pour
     * ce meme arret - indicateur de confiance du rapprochement, pas une donnee metier en soi.
     */
    #[ORM\Column(nullable: true)]
    private ?int $distanceReferentielOsm = null;

    #[ORM\ManyToOne]
    private ?Station $station = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArtId(): ?int
    {
        return $this->artId;
    }

    public function setArtId(int $artId): static
    {
        $this->artId = $artId;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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

    public function getVilleRef(): ?Ville
    {
        return $this->villeRef;
    }

    public function setVilleRef(?Ville $villeRef): static
    {
        $this->villeRef = $villeRef;

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

    public function isAccessibleFauteuilRoulant(): ?bool
    {
        return $this->accessibleFauteuilRoulant;
    }

    public function setAccessibleFauteuilRoulant(?bool $accessibleFauteuilRoulant): static
    {
        $this->accessibleFauteuilRoulant = $accessibleFauteuilRoulant;

        return $this;
    }

    public function isBanc(): ?bool
    {
        return $this->banc;
    }

    public function setBanc(?bool $banc): static
    {
        $this->banc = $banc;

        return $this;
    }

    public function isPoubelle(): ?bool
    {
        return $this->poubelle;
    }

    public function setPoubelle(?bool $poubelle): static
    {
        $this->poubelle = $poubelle;

        return $this;
    }

    public function isEclairage(): ?bool
    {
        return $this->eclairage;
    }

    public function setEclairage(?bool $eclairage): static
    {
        $this->eclairage = $eclairage;

        return $this;
    }

    public function isAbri(): ?bool
    {
        return $this->abri;
    }

    public function setAbri(?bool $abri): static
    {
        $this->abri = $abri;

        return $this;
    }

    public function isBandeTactile(): ?bool
    {
        return $this->bandeTactile;
    }

    public function setBandeTactile(?bool $bandeTactile): static
    {
        $this->bandeTactile = $bandeTactile;

        return $this;
    }

    public function getDistanceReferentielOsm(): ?int
    {
        return $this->distanceReferentielOsm;
    }

    public function setDistanceReferentielOsm(?int $distanceReferentielOsm): static
    {
        $this->distanceReferentielOsm = $distanceReferentielOsm;

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
