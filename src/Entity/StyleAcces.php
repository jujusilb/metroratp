<?php

namespace App\Entity;

use App\Repository\StyleAccesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StyleAccesRepository::class)]
class StyleAcces
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $label = null;

    /**
     * @var Collection<int, Acces>
     */
    #[ORM\OneToMany(targetEntity: Acces::class, mappedBy: 'styleAcces')]
    private Collection $acces;

    public function __construct()
    {
        $this->acces = new ArrayCollection();
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
     * @return Collection<int, Acces>
     */
    public function getAcces(): Collection
    {
        return $this->acces;
    }

    public function addAcces(Acces $acces): static
    {
        if (!$this->acces->contains($acces)) {
            $this->acces->add($acces);
            $acces->setStyleAcces($this);
        }

        return $this;
    }

    public function removeAcces(Acces $acces): static
    {
        if ($this->acces->removeElement($acces)) {
            if ($acces->getStyleAcces() === $this) {
                $acces->setStyleAcces(null);
            }
        }

        return $this;
    }
}
