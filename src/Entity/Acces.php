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
}
