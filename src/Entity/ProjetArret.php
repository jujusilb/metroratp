<?php

namespace App\Entity;

use App\Repository\ProjetArretRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Arret ou pole multimodal en projet ou en construction (dataset IDFM "projets_arrets_idf", 404
 * entrees) : futur du reseau, pas encore ouvert au public - donc jamais rattache a une Station ou
 * une Ligne existante (ce sont des entites distinctes, pas encore reelles).
 *
 * NATURE est un texte libre lisible ("arrêt" ou "pôle multimodal") mais MODE_/SOUS_MODE/STATUT/
 * PHASE sont des codes internes IDFM sans table de correspondance publiee dans les metadonnees du
 * dataset (verifie via l'API catalog) : stockes tels quels plutot que traduits en libelles
 * invente. Seule certitude documentee sur STATUT : echelle 1 (etudes prealables) a 10 (mise en
 * service), sans le detail des valeurs intermediaires.
 */
#[ORM\Entity(repositoryClass: ProjetArretRepository::class)]
class ProjetArret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nom de l'arret projete quand connu, sinon nom de l'operation (beaucoup d'entrees n'ont pas
     * encore de nom d'arret fixe a ce stade du projet).
     */
    #[ORM\Column(length: 150)]
    private ?string $label = null;

    #[ORM\Column(length: 150)]
    private ?string $nomProjet = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $operation = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $nature = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $mode = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $phase = null;

    #[ORM\Column]
    private bool $creation = false;

    #[ORM\Column]
    private bool $prolongement = false;

    #[ORM\Column]
    private bool $amelioration = false;

    #[ORM\Column]
    private bool $terminus = false;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNomProjet(): ?string
    {
        return $this->nomProjet;
    }

    public function setNomProjet(string $nomProjet): static
    {
        $this->nomProjet = $nomProjet;

        return $this;
    }

    public function getOperation(): ?string
    {
        return $this->operation;
    }

    public function setOperation(?string $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setNature(?string $nature): static
    {
        $this->nature = $nature;

        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getPhase(): ?string
    {
        return $this->phase;
    }

    public function setPhase(?string $phase): static
    {
        $this->phase = $phase;

        return $this;
    }

    public function isCreation(): bool
    {
        return $this->creation;
    }

    public function setCreation(bool $creation): static
    {
        $this->creation = $creation;

        return $this;
    }

    public function isProlongement(): bool
    {
        return $this->prolongement;
    }

    public function setProlongement(bool $prolongement): static
    {
        $this->prolongement = $prolongement;

        return $this;
    }

    public function isAmelioration(): bool
    {
        return $this->amelioration;
    }

    public function setAmelioration(bool $amelioration): static
    {
        $this->amelioration = $amelioration;

        return $this;
    }

    public function isTerminus(): bool
    {
        return $this->terminus;
    }

    public function setTerminus(bool $terminus): static
    {
        $this->terminus = $terminus;

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
}
