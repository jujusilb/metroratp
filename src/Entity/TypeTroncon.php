<?php

namespace App\Entity;

use App\Repository\TypeTronconRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeTronconRepository::class)]
class TypeTroncon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    private ?string $label = null;

    /**
     * @var Collection<int, Troncon>
     */
    #[ORM\OneToMany(targetEntity: Troncon::class, mappedBy: 'typeTroncon')]
    private Collection $troncons;

    public function __construct()
    {
        $this->troncons = new ArrayCollection();
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
     * @return Collection<int, Troncon>
     */
    public function getTroncons(): Collection
    {
        return $this->troncons;
    }

    public function addTroncon(Troncon $troncon): static
    {
        if (!$this->troncons->contains($troncon)) {
            $this->troncons->add($troncon);
            $troncon->setTypeTroncon($this);
        }

        return $this;
    }

    public function removeTroncon(Troncon $troncon): static
    {
        if ($this->troncons->removeElement($troncon)) {
            // set the owning side to null (unless already changed)
            if ($troncon->getTypeTroncon() === $this) {
                $troncon->setTypeTroncon(null);
            }
        }

        return $this;
    }
}
