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

    /**
     * Sens de circulation GTFS (direction_id, 0 ou 1) du quai precis (from_id) dont ce conseil est
     * issu - un quai precis correspond a un seul sens (verifie : tous les trips qui le desservent
     * partagent le meme direction_id). Permet de departager les 2 conseils opposes qu'une meme
     * Station+Ligne peut porter (Avant dans un sens, Arriere dans l'autre - voir
     * documentation/TODO.md, "Conseils de position dans la rame"). Null si le sens n'a pas pu etre
     * resolu (quai sans trip trouve dans l'instantane GTFS).
     */
    #[ORM\Column(nullable: true)]
    private ?int $directionId = null;

    /**
     * Terminus reel (trip_headsign GTFS) de ce sens de circulation - texte informatif seulement,
     * pas utilise pour le filtrage (voir prochaineStation).
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $terminusReel = null;

    /**
     * La toute proche Station reelle suivante, DANS CE SENS PRECIS de circulation (calculee depuis
     * la sequence GTFS complete du trip representatif). Sert a determiner, pour un troncon de
     * trajet calcule, si ce conseil correspond bien au sens reellement emprunte : le troncon va
     * bien de Station vers prochaineStation ? Plus fiable qu'une reconstruction de l'ordre complet
     * de la Ligne (fragile sur les lignes en maillage). Null si Station est un terminus reel dans
     * ce sens (rien apres).
     */
    #[ORM\ManyToOne]
    private ?Station $prochaineStation = null;

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

    public function getDirectionId(): ?int
    {
        return $this->directionId;
    }

    public function setDirectionId(?int $directionId): static
    {
        $this->directionId = $directionId;

        return $this;
    }

    public function getTerminusReel(): ?string
    {
        return $this->terminusReel;
    }

    public function setTerminusReel(?string $terminusReel): static
    {
        $this->terminusReel = $terminusReel;

        return $this;
    }

    public function getProchaineStation(): ?Station
    {
        return $this->prochaineStation;
    }

    public function setProchaineStation(?Station $prochaineStation): static
    {
        $this->prochaineStation = $prochaineStation;

        return $this;
    }
}
