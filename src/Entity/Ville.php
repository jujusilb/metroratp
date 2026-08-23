<?php

namespace App\Entity;

use App\Repository\VilleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Commune (referentiel officiel geo.api.gouv.fr), avec sa frontiere geographique reelle -
 * permet d'afficher les limites d'une ville sur la carte plutot que le seul nom en texte libre
 * de Station::ville. Perimetre Ile-de-France uniquement (8 departements), voir
 * documentation/geo-communes/ et documentation/TODO.md.
 */
#[ORM\Entity(repositoryClass: VilleRepository::class)]
class Ville
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $label = null;

    /**
     * Code INSEE (identifiant officiel de la commune, ex: "75056" pour Paris) - cle naturelle du
     * referentiel geo.api.gouv.fr, sert au rattachement depuis Station::ville (voir
     * app:importer-villes).
     */
    #[ORM\Column(length: 5, unique: true)]
    private ?string $codeInsee = null;

    /**
     * Geometrie GeoJSON brute du contour de la commune (Polygon ou MultiPolygon, WGS84), depuis
     * geo.api.gouv.fr (parametre geometry=contour). Null si jamais importee.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $frontiere = null;

    /**
     * @var Collection<int, Station>
     */
    #[ORM\OneToMany(targetEntity: Station::class, mappedBy: 'villeRef')]
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

    public function getCodeInsee(): ?string
    {
        return $this->codeInsee;
    }

    public function setCodeInsee(string $codeInsee): static
    {
        $this->codeInsee = $codeInsee;

        return $this;
    }

    public function getFrontiere(): ?array
    {
        return $this->frontiere;
    }

    public function setFrontiere(?array $frontiere): static
    {
        $this->frontiere = $frontiere;

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
            $station->setVilleRef($this);
        }

        return $this;
    }

    public function removeStation(Station $station): static
    {
        if ($this->stations->removeElement($station)) {
            if ($station->getVilleRef() === $this) {
                $station->setVilleRef(null);
            }
        }

        return $this;
    }
}
