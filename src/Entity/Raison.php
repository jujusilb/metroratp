<?php

namespace App\Entity;

use App\Repository\RaisonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catalogue des raisons pour lesquelles une Station est consideree inactive (aucun service
 * exploitable aujourd'hui). Pas de champ Station.actif separe : la seule presence d'au moins une
 * Raison liee signifie "inactive" (absence = active), pour eviter une donnee redondante qui
 * pourrait se desynchroniser de la realite. Une Station peut etre inactive pour plusieurs raisons
 * a la fois (ex: fermee pendant la guerre ET station suivante devenue trop proche), d'ou le
 * ManyToMany plutot qu'un simple raisonId.
 */
#[ORM\Entity(repositoryClass: RaisonRepository::class)]
class Raison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $label = null;

    /**
     * @var Collection<int, Station>
     */
    #[ORM\ManyToMany(targetEntity: Station::class, inversedBy: 'raisons')]
    private Collection $stations;

    public function __construct()
    {
        $this->stations = new ArrayCollection();
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
     * @return Collection<int, Station>
     */
    public function getStations(): Collection
    {
        return $this->stations;
    }

    public function addStation(Station $station): static
    {
        if (!$this->stations->contains($station)) {
            $this->stations->add($station);
        }

        return $this;
    }

    public function removeStation(Station $station): static
    {
        $this->stations->removeElement($station);

        return $this;
    }
}
