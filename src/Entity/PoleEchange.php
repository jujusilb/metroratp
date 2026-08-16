<?php

namespace App\Entity;

use App\Repository\PoleEchangeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pole d'echange IDFM (dataset "poles-d-echange") : grand hub multimodal identifie officiellement
 * (gare, aeroport...) regroupant plusieurs Station (souvent plusieurs modes/operateurs distincts
 * au meme endroit reel). Une Station appartient a au plus un Pole (voir Station::$poleEchange) ;
 * un Pole regroupe plusieurs Station.
 *
 * Seulement 10 poles dans le dataset source (poles-d-echange.csv), sans cle de rattachement
 * directe vers les Station. Le rattachement Station::poleEchange se fait via relations.csv
 * (referentiel officiel PdE/ZdC/ZdA/ArR/ArT), qui donne pour chaque PdEId ses ZdCId - et ZdCId
 * correspond exactement a Station::codeExterne. Voir app:importer-poles-echange et
 * documentation/commande.md pour le detail de cette jointure (34 Stations rattachees).
 */
#[ORM\Entity(repositoryClass: PoleEchangeRepository::class)]
class PoleEchange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $codeExterne = null;

    #[ORM\Column(length: 150)]
    private ?string $label = null;

    /**
     * @var Collection<int, Station>
     */
    #[ORM\OneToMany(targetEntity: Station::class, mappedBy: 'poleEchange')]
    private Collection $stations;

    public function __construct()
    {
        $this->stations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeExterne(): ?string
    {
        return $this->codeExterne;
    }

    public function setCodeExterne(string $codeExterne): static
    {
        $this->codeExterne = $codeExterne;

        return $this;
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
            $station->setPoleEchange($this);
        }

        return $this;
    }

    public function removeStation(Station $station): static
    {
        if ($this->stations->removeElement($station)) {
            if ($station->getPoleEchange() === $this) {
                $station->setPoleEchange(null);
            }
        }

        return $this;
    }
}
