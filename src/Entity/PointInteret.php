<?php

namespace App\Entity;

use App\Repository\PointInteretRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lieu remarquable a proximite d'une ou plusieurs Station (musee, monument, hopital, jardin...),
 * dans l'esprit de la section "a proximite" d'une fiche Wikipedia de station. Source :
 * positionnement-dans-la-rame (IDFM) - le champ destination d'une sortie (Acces) contient parfois
 * le nom d'un vrai lieu plutot qu'une simple adresse de rue (deja couverte par Acces.label), voir
 * documentation/scripts/extraire_points_interet.php. Un meme lieu peut etre proche de plusieurs
 * Station (ex: le Forum des Halles pres de Chatelet, Chatelet-Les Halles et Les Halles).
 */
#[ORM\Entity(repositoryClass: PointInteretRepository::class)]
class PointInteret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    private ?string $label = null;

    /**
     * @var Collection<int, Station>
     */
    #[ORM\ManyToMany(targetEntity: Station::class, inversedBy: 'pointsInteret')]
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
