<?php

namespace App\Entity;

use App\Repository\MaterielRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaterielRepository::class)]
class Materiel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $label = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $anneeProduction = null;

    /**
     * Constructeur(s), depuis Wikidata (propriete P176) - plusieurs entreprises separees par
     * " / " quand la donnee source en liste plusieurs (sous-traitance/consortium de fabrication).
     */
    #[ORM\Column(length: 300, nullable: true)]
    private ?string $constructeur = null;

    /**
     * Vitesse maximale en service, depuis Wikidata (propriete P2052, deja en km/h a la source).
     * Rarement renseignee sur Wikidata pour le materiel roulant parisien : rester null plutot
     * que d'estimer quand absent.
     */
    #[ORM\Column(nullable: true)]
    private ?int $vitesseMaxKmh = null;

    #[ORM\ManyToOne(inversedBy: 'materiels')]
    private ?TypeMateriel $typeMateriel = null;

    /**
     * @var Collection<int, MaterielLigne>
     */
    #[ORM\OneToMany(targetEntity: MaterielLigne::class, mappedBy: 'materiel')]
    private Collection $materielLignes;

    /**
     * @var Collection<int, MaterielDepot>
     */
    #[ORM\OneToMany(targetEntity: MaterielDepot::class, mappedBy: 'materiel')]
    private Collection $materielDepots;

    public function __construct()
    {
        $this->materielLignes = new ArrayCollection();
        $this->materielDepots = new ArrayCollection();
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

    public function getAnneeProduction(): ?string
    {
        return $this->anneeProduction;
    }

    public function setAnneeProduction(?string $anneeProduction): static
    {
        $this->anneeProduction = $anneeProduction;

        return $this;
    }

    public function getConstructeur(): ?string
    {
        return $this->constructeur;
    }

    public function setConstructeur(?string $constructeur): static
    {
        $this->constructeur = $constructeur;

        return $this;
    }

    public function getVitesseMaxKmh(): ?int
    {
        return $this->vitesseMaxKmh;
    }

    public function setVitesseMaxKmh(?int $vitesseMaxKmh): static
    {
        $this->vitesseMaxKmh = $vitesseMaxKmh;

        return $this;
    }

    public function getTypeMateriel(): ?TypeMateriel
    {
        return $this->typeMateriel;
    }

    public function setTypeMateriel(?TypeMateriel $typeMateriel): static
    {
        $this->typeMateriel = $typeMateriel;

        return $this;
    }

    /**
     * @return Collection<int, MaterielLigne>
     */
    public function getMaterielLignes(): Collection
    {
        return $this->materielLignes;
    }

    public function addMaterielLigne(MaterielLigne $materielLigne): static
    {
        if (!$this->materielLignes->contains($materielLigne)) {
            $this->materielLignes->add($materielLigne);
            $materielLigne->setMateriel($this);
        }

        return $this;
    }

    public function removeMaterielLigne(MaterielLigne $materielLigne): static
    {
        if ($this->materielLignes->removeElement($materielLigne)) {
            // set the owning side to null (unless already changed)
            if ($materielLigne->getMateriel() === $this) {
                $materielLigne->setMateriel(null);
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
            $materielDepot->setMateriel($this);
        }

        return $this;
    }

    public function removeMaterielDepot(MaterielDepot $materielDepot): static
    {
        if ($this->materielDepots->removeElement($materielDepot)) {
            if ($materielDepot->getMateriel() === $this) {
                $materielDepot->setMateriel(null);
            }
        }

        return $this;
    }
}
