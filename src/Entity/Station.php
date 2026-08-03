<?php

namespace App\Entity;

use App\Repository\StationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StationRepository::class)]
class Station
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $label = null;

    /**
     * Position sur le plan schematique officiel du reseau (coordonnees Ile-de-France Mobilites,
     * pas des coordonnees geographiques) : voir la commande app:importer-coordonnees-schema.
     * Null si la station n'a pas ete trouvee dans la source (ex: extension recente).
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $schemaX = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $schemaY = null;

    /**
     * @var Collection<int, Sortie>
     */
    #[ORM\OneToMany(targetEntity: Sortie::class, mappedBy: 'station')]
    private Collection $sorties;

    /**
     * @var Collection<int, Desserte>
     */
    #[ORM\OneToMany(targetEntity: Desserte::class, mappedBy: 'station')]
    private Collection $dessertes;

    public function __construct()
    {
        $this->sorties = new ArrayCollection();
        $this->dessertes = new ArrayCollection();
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

    public function getSchemaX(): ?float
    {
        return $this->schemaX;
    }

    public function setSchemaX(?float $schemaX): static
    {
        $this->schemaX = $schemaX;

        return $this;
    }

    public function getSchemaY(): ?float
    {
        return $this->schemaY;
    }

    public function setSchemaY(?float $schemaY): static
    {
        $this->schemaY = $schemaY;

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
            $sorty->setStation($this);
        }

        return $this;
    }

    public function removeSorty(Sortie $sorty): static
    {
        if ($this->sorties->removeElement($sorty)) {
            // set the owning side to null (unless already changed)
            if ($sorty->getStation() === $this) {
                $sorty->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Desserte>
     */
    public function getDessertes(): Collection
    {
        return $this->dessertes;
    }

    public function addDesserte(Desserte $desserte): static
    {
        if (!$this->dessertes->contains($desserte)) {
            $this->dessertes->add($desserte);
            $desserte->setStation($this);
        }

        return $this;
    }

    public function removeDesserte(Desserte $desserte): static
    {
        if ($this->dessertes->removeElement($desserte)) {
            // set the owning side to null (unless already changed)
            if ($desserte->getStation() === $this) {
                $desserte->setStation(null);
            }
        }

        return $this;
    }
}
