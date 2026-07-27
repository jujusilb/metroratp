<?php

namespace App\Entity;

use App\Repository\TypeDesserteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeDesserteRepository::class)]
class TypeDesserte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    private ?string $label = null;

    /**
     * @var Collection<int, TronconDesserte>
     */
    #[ORM\OneToMany(targetEntity: TronconDesserte::class, mappedBy: 'typeDesserte')]
    private Collection $tronconDessertes;

    public function __construct()
    {
        $this->tronconDessertes = new ArrayCollection();
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
     * @return Collection<int, TronconDesserte>
     */
    public function getTronconDessertes(): Collection
    {
        return $this->tronconDessertes;
    }
}
