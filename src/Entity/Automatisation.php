<?php

namespace App\Entity;

use App\Repository\AutomatisationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AutomatisationRepository::class)]
class Automatisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $label = null;

    /**
     * @var Collection<int, AutomatisationLigne>
     */
    #[ORM\OneToMany(targetEntity: AutomatisationLigne::class, mappedBy: 'automatisation')]
    private Collection $automatisationLignes;

    public function __construct()
    {
        $this->automatisationLignes = new ArrayCollection();
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
     * @return Collection<int, AutomatisationLigne>
     */
    public function getAutomatisationLignes(): Collection
    {
        return $this->automatisationLignes;
    }

    public function addAutomatisationLigne(AutomatisationLigne $automatisationLigne): static
    {
        if (!$this->automatisationLignes->contains($automatisationLigne)) {
            $this->automatisationLignes->add($automatisationLigne);
            $automatisationLigne->setAutomatisation($this);
        }

        return $this;
    }

    public function removeAutomatisationLigne(AutomatisationLigne $automatisationLigne): static
    {
        if ($this->automatisationLignes->removeElement($automatisationLigne)) {
            if ($automatisationLigne->getAutomatisation() === $this) {
                $automatisationLigne->setAutomatisation(null);
            }
        }

        return $this;
    }
}
