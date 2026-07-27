<?php

namespace App\Entity;

use App\Repository\TronconRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TronconRepository::class)]
class Troncon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $parcours = null;

    #[ORM\ManyToOne(inversedBy: 'troncons')]
    private ?TypeTroncon $typeTroncon = null;

    /**
     * @var Collection<int, TronconDesserte>
     */
    #[ORM\OneToMany(targetEntity: TronconDesserte::class, mappedBy: 'troncon')]
    private Collection $tronconDessertes;

    public function __construct()
    {
        $this->tronconDessertes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParcours(): ?string
    {
        return $this->parcours;
    }

    public function setParcours(?string $parcours): static
    {
        $this->parcours = $parcours;

        return $this;
    }

    public function getTypeTroncon(): ?TypeTroncon
    {
        return $this->typeTroncon;
    }

    public function setTypeTroncon(?TypeTroncon $typeTroncon): static
    {
        $this->typeTroncon = $typeTroncon;

        return $this;
    }

    /**
     * @return Collection<int, TronconDesserte>
     */
    public function getTronconDessertes(): Collection
    {
        return $this->tronconDessertes;
    }

    /**
     * La desserte jouant le role donne ("Depart" ou "Arrivee") pour ce troncon,
     * eventuellement en excluant une desserte precise (utile pour trouver "l'autre
     * bout" du troncon quand on connait deja un cote).
     */
    public function getDesserteForRole(string $roleLabel, ?Desserte $excluding = null): ?Desserte
    {
        foreach ($this->tronconDessertes as $tronconDesserte) {
            if ($tronconDesserte->getTypeDesserte()?->getLabel() !== $roleLabel) {
                continue;
            }
            $desserte = $tronconDesserte->getDesserte();
            if (null !== $excluding && $desserte === $excluding) {
                continue;
            }

            return $desserte;
        }

        return null;
    }

    /**
     * Les (jusqu'a) deux sens de circulation possibles sur ce troncon (aller et retour) : pour
     * chacun, le depart, l'arrivee, et la direction terminus deduite d'une mission existante
     * empruntant ce troncon dans ce sens (s'il y en a une).
     *
     * @return array<int, array{depart: ?Desserte, arrivee: ?Desserte, direction: ?Desserte}>
     */
    public function getSensCirculation(): array
    {
        $sens = [];

        foreach ($this->tronconDessertes as $tronconDesserte) {
            if ('Départ' !== $tronconDesserte->getTypeDesserte()?->getLabel()) {
                continue;
            }

            $depart = $tronconDesserte->getDesserte();
            $direction = null;
            foreach ($tronconDesserte->getMissions() as $mission) {
                $direction = $mission->getDirection();
                break;
            }

            $sens[] = [
                'depart' => $depart,
                'arrivee' => $this->getDesserteForRole('Arrivée', $depart),
                'direction' => $direction,
            ];
        }

        return $sens;
    }
}
