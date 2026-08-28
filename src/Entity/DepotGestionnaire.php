<?php

namespace App\Entity;

use App\Repository\DepotGestionnaireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Affectation d'un Depot a un Gestionnaire (exploitant), avec periode - meme raisonnement que
 * DepotLigne : un depot physique n'appartient qu'a un seul exploitant a la fois (pas de partage
 * simultane), mais l'exploitant peut changer lors d'un nouvel appel d'offres IDFM (delegation de
 * service public), d'ou une entite datee plutot qu'un simple champ gestionnaire_id sur Depot.
 */
#[ORM\Entity(repositoryClass: DepotGestionnaireRepository::class)]
class DepotGestionnaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $arrivee = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $fin = null;

    #[ORM\ManyToOne(inversedBy: 'depotGestionnaires')]
    private ?Depot $depot = null;

    #[ORM\ManyToOne(inversedBy: 'depotGestionnaires')]
    private ?Gestionnaire $gestionnaire = null;

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

    public function getGestionnaire(): ?Gestionnaire
    {
        return $this->gestionnaire;
    }

    public function setGestionnaire(?Gestionnaire $gestionnaire): static
    {
        $this->gestionnaire = $gestionnaire;

        return $this;
    }
}
