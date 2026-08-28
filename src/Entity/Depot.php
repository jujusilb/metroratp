<?php

namespace App\Entity;

use App\Repository\DepotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Depot/centre bus (un depot dessert plusieurs Ligne, un materiel de bus est affecte a un depot
 * plutot qu'a une Ligne precise - contrairement au ferroviaire, le materiel roulant bus n'est pas
 * documente ligne par ligne mais par depot. Voir MaterielDepot : le materiel d'une Ligne de bus se
 * deduit alors par jointure Ligne -> Depot -> MaterielDepot -> Materiel.
 */
#[ORM\Entity(repositoryClass: DepotRepository::class)]
class Depot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $label = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $adresse = null;

    #[ORM\ManyToOne]
    private ?Ville $ville = null;

    /**
     * @var Collection<int, DepotLigne>
     */
    #[ORM\OneToMany(targetEntity: DepotLigne::class, mappedBy: 'depot')]
    private Collection $depotLignes;

    /**
     * @var Collection<int, MaterielDepot>
     */
    #[ORM\OneToMany(targetEntity: MaterielDepot::class, mappedBy: 'depot')]
    private Collection $materielDepots;

    /**
     * @var Collection<int, DepotGestionnaire>
     */
    #[ORM\OneToMany(targetEntity: DepotGestionnaire::class, mappedBy: 'depot')]
    private Collection $depotGestionnaires;

    public function __construct()
    {
        $this->depotLignes = new ArrayCollection();
        $this->materielDepots = new ArrayCollection();
        $this->depotGestionnaires = new ArrayCollection();
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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getVille(): ?Ville
    {
        return $this->ville;
    }

    public function setVille(?Ville $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    /**
     * @return Collection<int, DepotLigne>
     */
    public function getDepotLignes(): Collection
    {
        return $this->depotLignes;
    }

    public function addDepotLigne(DepotLigne $depotLigne): static
    {
        if (!$this->depotLignes->contains($depotLigne)) {
            $this->depotLignes->add($depotLigne);
            $depotLigne->setDepot($this);
        }

        return $this;
    }

    public function removeDepotLigne(DepotLigne $depotLigne): static
    {
        if ($this->depotLignes->removeElement($depotLigne)) {
            if ($depotLigne->getDepot() === $this) {
                $depotLigne->setDepot(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MaterielDepot>
     */
    public function getMaterielDepots(): Collection
    {
        return $this->materielDepots;
    }

    public function addMaterielDepot(MaterielDepot $materielDepot): static
    {
        if (!$this->materielDepots->contains($materielDepot)) {
            $this->materielDepots->add($materielDepot);
            $materielDepot->setDepot($this);
        }

        return $this;
    }

    public function removeMaterielDepot(MaterielDepot $materielDepot): static
    {
        if ($this->materielDepots->removeElement($materielDepot)) {
            if ($materielDepot->getDepot() === $this) {
                $materielDepot->setDepot(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DepotGestionnaire>
     */
    public function getDepotGestionnaires(): Collection
    {
        return $this->depotGestionnaires;
    }

    public function addDepotGestionnaire(DepotGestionnaire $depotGestionnaire): static
    {
        if (!$this->depotGestionnaires->contains($depotGestionnaire)) {
            $this->depotGestionnaires->add($depotGestionnaire);
            $depotGestionnaire->setDepot($this);
        }

        return $this;
    }

    public function removeDepotGestionnaire(DepotGestionnaire $depotGestionnaire): static
    {
        if ($this->depotGestionnaires->removeElement($depotGestionnaire)) {
            if ($depotGestionnaire->getDepot() === $this) {
                $depotGestionnaire->setDepot(null);
            }
        }

        return $this;
    }
}
