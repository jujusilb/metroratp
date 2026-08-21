<?php

namespace App\Entity;

use App\Repository\TronconDesserteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TronconDesserteRepository::class)]
class TronconDesserte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tronconDessertes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Troncon $troncon = null;

    #[ORM\ManyToOne(inversedBy: 'tronconDessertes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Desserte $desserte = null;

    #[ORM\ManyToOne(inversedBy: 'tronconDessertes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeDesserte $typeDesserte = null;

    /**
     * Duree reelle (secondes) du trajet DEPUIS cette desserte jusqu'a l'autre bout du troncon -
     * uniquement significatif sur les lignes de role "Depart" (voir TypeDesserte). Permet de
     * representer un temps asymetrique entre l'aller et le retour (quais decales, ex: Liege sur la
     * ligne 13 metro : 89s vers Saint-Lazare, 65s vers Place de Clichy) - Troncon::dureeReelleSecondes
     * reste le repli symetrique utilise quand cette valeur plus precise est absente. Voir
     * app:importer-durees-troncon et TrajetFinder::construireGraphe().
     */
    #[ORM\Column(nullable: true)]
    private ?int $dureeReelleSecondes = null;

    /**
     * @var Collection<int, Mission>
     */
    #[ORM\OneToMany(targetEntity: Mission::class, mappedBy: 'tronconDesserte')]
    private Collection $missions;

    public function __construct()
    {
        $this->missions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTroncon(): ?Troncon
    {
        return $this->troncon;
    }

    public function setTroncon(?Troncon $troncon): static
    {
        $this->troncon = $troncon;

        return $this;
    }

    public function getDesserte(): ?Desserte
    {
        return $this->desserte;
    }

    public function setDesserte(?Desserte $desserte): static
    {
        $this->desserte = $desserte;

        return $this;
    }

    public function getTypeDesserte(): ?TypeDesserte
    {
        return $this->typeDesserte;
    }

    public function setTypeDesserte(?TypeDesserte $typeDesserte): static
    {
        $this->typeDesserte = $typeDesserte;

        return $this;
    }

    public function getDureeReelleSecondes(): ?int
    {
        return $this->dureeReelleSecondes;
    }

    public function setDureeReelleSecondes(?int $dureeReelleSecondes): static
    {
        $this->dureeReelleSecondes = $dureeReelleSecondes;

        return $this;
    }

    /**
     * @return Collection<int, Mission>
     */
    public function getMissions(): Collection
    {
        return $this->missions;
    }
}
