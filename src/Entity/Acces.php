<?php

namespace App\Entity;

use App\Repository\AccesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccesRepository::class)]
class Acces
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $label;

    /**
     * @var Collection<int, Sortie>
     */
    #[ORM\OneToMany(targetEntity: Sortie::class, mappedBy: 'acces')]
    private Collection $sorties;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $numero = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isAccessible = null;

    /**
     * AccId du referentiel IDFM (dataset "acces" / GTFS StopPlaceEntrance) : permet de retrouver
     * cet Acces depuis d'autres jeux de donnees IDFM qui reference ce meme identifiant (ex:
     * positionnement-dans-la-rame). Null pour les Acces crees manuellement.
     */
    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $codeExterne = null;

    /**
     * Coordonnees geographiques reelles (WGS84), depuis stops.txt (GTFS IDFM, location_type=2 -
     * meme source que Station::latitude/longitude) : voir app:construire-acces-sorties. Sert a
     * afficher la position de chaque acces sur une petite carte, sur la fiche de sa Station.
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    /**
     * Distance/temps de marche reels (pas une approximation) depuis cet Acces jusqu'au quai le
     * plus proche de la Station, depuis pathways.txt (GTFS IDFM). Quand un Acces dessert plusieurs
     * quais (lignes differentes), on garde le plus proche (voir app:importer-temps-marche-acces).
     * Null si aucun pathway trouve pour cet Acces (~5% des cas).
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $distanceMarcheMetres = null;

    #[ORM\Column(nullable: true)]
    private ?int $tempsMarcheSecondes = null;

    /**
     * Champs supplementaires de pathways.txt (GTFS), stockes meme si vides sur 100% des lignes du
     * jeu de donnees actuel (verifie le 2026-08-15) : rien ne garantit que ce restera le cas dans
     * un futur export IDFM, et ces details (nombre de marches, pente, largeur, signaletique) ont
     * leur place ici pour l'ambition encyclopedique du site, meme quand ils sont anecdotiques.
     */
    #[ORM\Column(nullable: true)]
    private ?int $nombreMarches = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $penteMaxPourcent = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $largeurMinMetres = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $signalisation = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $signalisationInverse = null;

    /**
     * false = le cheminement vers le quai le plus proche ne se fait que dans un sens (ex: sortie
     * uniquement). true/null = bidirectionnel ou inconnu.
     */
    #[ORM\Column(nullable: true)]
    private ?bool $cheminementBidirectionnel = null;

    #[ORM\ManyToOne(inversedBy: 'acces')]
    private ?StyleAcces $styleAcces = null;

    /**
     * AccIsEntry/AccIsExit du dataset "acces" IDFM (data.iledefrance-mobilites.fr) : si cet Acces
     * peut etre emprunte pour entrer/sortir de la station (ex: certaines grilles ne sont que
     * sortie). Distinct de cheminementBidirectionnel (qui porte sur le cheminement pietonnier vers
     * le quai, pas sur l'usage autorise de l'entree elle-meme). Null pour les Acces crees a la
     * main ou absents du dataset "acces" (voir app:construire-acces-sorties).
     */
    #[ORM\Column(nullable: true)]
    private ?bool $estEntree = null;

    #[ORM\Column(nullable: true)]
    private ?bool $estSortie = null;

    public function __construct()
    {
        $this->sorties = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, Sortie>
     */
    public function getSorties(): Collection
    {
        return $this->sorties;
    }

    public function addSorty(Sortie $sorty): static
    {
        if (!$this->sorties->contains($sorty)) {
            $this->sorties->add($sorty);
            $sorty->setAcces($this);
        }

        return $this;
    }

    public function removeSorty(Sortie $sorty): static
    {
        if ($this->sorties->removeElement($sorty)) {
            // set the owning side to null (unless already changed)
            if ($sorty->getAcces() === $this) {
                $sorty->setAcces(null);
            }
        }

        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function isAccessible(): ?bool
    {
        return $this->isAccessible;
    }

    public function setIsAccessible(?bool $isAccessible): static
    {
        $this->isAccessible = $isAccessible;

        return $this;
    }

    public function getCodeExterne(): ?string
    {
        return $this->codeExterne;
    }

    public function setCodeExterne(?string $codeExterne): static
    {
        $this->codeExterne = $codeExterne;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getDistanceMarcheMetres(): ?float
    {
        return $this->distanceMarcheMetres;
    }

    public function setDistanceMarcheMetres(?float $distanceMarcheMetres): static
    {
        $this->distanceMarcheMetres = $distanceMarcheMetres;

        return $this;
    }

    public function getTempsMarcheSecondes(): ?int
    {
        return $this->tempsMarcheSecondes;
    }

    public function setTempsMarcheSecondes(?int $tempsMarcheSecondes): static
    {
        $this->tempsMarcheSecondes = $tempsMarcheSecondes;

        return $this;
    }

    public function getNombreMarches(): ?int
    {
        return $this->nombreMarches;
    }

    public function setNombreMarches(?int $nombreMarches): static
    {
        $this->nombreMarches = $nombreMarches;

        return $this;
    }

    public function getPenteMaxPourcent(): ?float
    {
        return $this->penteMaxPourcent;
    }

    public function setPenteMaxPourcent(?float $penteMaxPourcent): static
    {
        $this->penteMaxPourcent = $penteMaxPourcent;

        return $this;
    }

    public function getLargeurMinMetres(): ?float
    {
        return $this->largeurMinMetres;
    }

    public function setLargeurMinMetres(?float $largeurMinMetres): static
    {
        $this->largeurMinMetres = $largeurMinMetres;

        return $this;
    }

    public function getSignalisation(): ?string
    {
        return $this->signalisation;
    }

    public function setSignalisation(?string $signalisation): static
    {
        $this->signalisation = $signalisation;

        return $this;
    }

    public function getSignalisationInverse(): ?string
    {
        return $this->signalisationInverse;
    }

    public function setSignalisationInverse(?string $signalisationInverse): static
    {
        $this->signalisationInverse = $signalisationInverse;

        return $this;
    }

    public function isCheminementBidirectionnel(): ?bool
    {
        return $this->cheminementBidirectionnel;
    }

    public function setCheminementBidirectionnel(?bool $cheminementBidirectionnel): static
    {
        $this->cheminementBidirectionnel = $cheminementBidirectionnel;

        return $this;
    }

    public function getStyleAcces(): ?StyleAcces
    {
        return $this->styleAcces;
    }

    public function setStyleAcces(?StyleAcces $styleAcces): static
    {
        $this->styleAcces = $styleAcces;

        return $this;
    }

    public function isEstEntree(): ?bool
    {
        return $this->estEntree;
    }

    public function setEstEntree(?bool $estEntree): static
    {
        $this->estEntree = $estEntree;

        return $this;
    }

    public function isEstSortie(): ?bool
    {
        return $this->estSortie;
    }

    public function setEstSortie(?bool $estSortie): static
    {
        $this->estSortie = $estSortie;

        return $this;
    }
}
