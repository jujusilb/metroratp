<?php

namespace App\Entity;

use App\Repository\StatutTacheRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Statut possible d'une Tache (table de reference plutot qu'une enum PHP, meme pattern que
 * StyleStation/TypeTransport/TypeMateriel dans ce projet) : A_FAIRE, EN_COURS, SUSPENDUE, ACHEVEE.
 */
#[ORM\Entity(repositoryClass: StatutTacheRepository::class)]
class StatutTache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $label = null;

    /**
     * @var Collection<int, Tache>
     */
    #[ORM\OneToMany(targetEntity: Tache::class, mappedBy: 'statut')]
    private Collection $taches;

    public function __construct()
    {
        $this->taches = new ArrayCollection();
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
     * @return Collection<int, Tache>
     */
    public function getTaches(): Collection
    {
        return $this->taches;
    }

    public function addTache(Tache $tache): static
    {
        if (!$this->taches->contains($tache)) {
            $this->taches->add($tache);
            $tache->setStatut($this);
        }

        return $this;
    }

    public function removeTache(Tache $tache): static
    {
        if ($this->taches->removeElement($tache)) {
            if ($tache->getStatut() === $this) {
                // Pas de setStatut(null) : Tache::statut est NOT NULL (JoinColumn nullable: false).
            }
        }

        return $this;
    }
}
