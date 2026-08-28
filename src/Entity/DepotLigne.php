<?php

namespace App\Entity;

use App\Repository\DepotLigneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Affectation d'un Depot a une Ligne, avec periode (meme schema que MaterielLigne/MaterielDepot) :
 * une Ligne de bus peut changer de depot d'affectation au fil du temps (reorganisation, passage a
 * l'electrique...), et peut aussi etre desservie par plusieurs Depot a la fois - d'ou une entite
 * dediee plutot qu'un simple ManyToMany, qui ne pourrait pas porter de date.
 */
#[ORM\Entity(repositoryClass: DepotLigneRepository::class)]
class DepotLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $arrivee = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $fin = null;

    #[ORM\ManyToOne(inversedBy: 'depotLignes')]
    private ?Depot $depot = null;

    #[ORM\ManyToOne(inversedBy: 'depotLignes')]
    private ?Ligne $ligne = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArrivee(): ?\DateTime
    {
        return $this->arrivee;
    }

    public function setArrivee(?\DateTime $arrivee): static
    {
        $this->arrivee = $arrivee;

        return $this;
    }

    public function getFin(): ?\DateTime
    {
        return $this->fin;
    }

    public function setFin(?\DateTime $fin): static
    {
        $this->fin = $fin;

        return $this;
    }

    public function getDepot(): ?Depot
    {
        return $this->depot;
    }

    public function setDepot(?Depot $depot): static
    {
        $this->depot = $depot;

        return $this;
    }

    public function getLigne(): ?Ligne
    {
        return $this->ligne;
    }

    public function setLigne(?Ligne $ligne): static
    {
        $this->ligne = $ligne;

        return $this;
    }
}
