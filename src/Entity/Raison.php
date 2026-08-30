<?php

namespace App\Entity;

use App\Repository\RaisonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catalogue des raisons pour lesquelles une Station OU une Desserte est consideree inactive
 * (aucun service exploitable aujourd'hui). Pas de champ "actif" separe sur ces entites : la seule
 * presence d'au moins une Raison liee signifie "inactive" (absence = active), pour eviter une
 * donnee redondante qui pourrait se desynchroniser de la realite. Une Station/Desserte peut etre
 * inactive pour plusieurs raisons a la fois (ex: fermee pendant la guerre ET station suivante
 * devenue trop proche), d'ou le ManyToMany plutot qu'un simple raisonId.
 *
 * Les deux relations coexistent car une Station peut rester active (vrai lieu, vrai service
 * aujourd'hui - ex: un arret de bus) tout en ayant UNE Desserte precise definitivement morte (ex:
 * un quai de metro jamais mis en service ou ferme sans reouverture) : l'inactivite se constate au
 * niveau ou elle est reellement vraie, pas force artificiellement au niveau Station entiere
 * (remarque utilisateur, cf. "stations fantomes" dans TODO.md).
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

    /**
     * @var Collection<int, Desserte>
     */
    #[ORM\ManyToMany(targetEntity: Desserte::class, inversedBy: 'raisons')]
    private Collection $dessertes;

    public function __construct()
    {
        $this->stations = new ArrayCollection();
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
        }

        return $this;
    }

    public function removeDesserte(Desserte $desserte): static
    {
        $this->dessertes->removeElement($desserte);

        return $this;
    }
}
