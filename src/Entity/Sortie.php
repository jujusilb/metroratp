<?php

namespace App\Entity;

use App\Repository\SortieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SortieRepository::class)]
class Sortie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sorties')]
    private ?Acces $acces = null;

    #[ORM\ManyToOne(inversedBy: 'sorties')]
    private ?Station $station = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAcces(): ?Acces
    {
        return $this->acces;
    }

    public function setAcces(?Acces $acces): static
    {
        $this->acces = $acces;

        return $this;
    }

    public function getStation(): ?Station
    {
        return $this->station;
    }

    public function setStation(?Station $station): static
    {
        $this->station = $station;

        return $this;
    }
}
