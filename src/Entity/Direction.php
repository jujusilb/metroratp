<?php

namespace App\Entity;

use App\Repository\DirectionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une direction possible sur une ligne (ex: ligne 4 direction Porte de Clignancourt), definie
 * par son terminus (une Desserte existante ailleurs sur le reseau). Referentiel normalise :
 * pour une ligne donnee, les directions possibles sont toujours les memes, reutilisees par
 * Mission (quel trajet on fait) et Correspondance (de quel quai vers quel quai).
 */
#[ORM\Entity(repositoryClass: DirectionRepository::class)]
#[ORM\UniqueConstraint(name: 'direction_unique_ligne_terminus', columns: ['ligne_id', 'desserte_terminus_id'])]
class Direction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'directions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ligne $ligne = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, name: 'desserte_terminus_id')]
    private ?Desserte $desserteTerminus = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDesserteTerminus(): ?Desserte
    {
        return $this->desserteTerminus;
    }

    public function setDesserteTerminus(?Desserte $desserteTerminus): static
    {
        $this->desserteTerminus = $desserteTerminus;

        return $this;
    }

    /**
     * Raccourci vers la station terminus, pour que les templates existants
     * (ex: {{ mission.direction.station.label }}) continuent de fonctionner sans changement.
     */
    public function getStation(): ?Station
    {
        return $this->desserteTerminus?->getStation();
    }
}
