<?php

namespace App\Entity;

use App\Repository\MissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MissionRepository::class)]
class Mission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $numero = null;

    #[ORM\ManyToOne(inversedBy: 'missions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    /**
     * Pointe vers la ligne de troncon_desserte jouant le role "Depart" pour le sens
     * de circulation de cette mission. L'arrivee se deduit : c'est l'autre desserte
     * du meme troncon, cote "Arrivee" (un troncon ne touche jamais que 2 dessertes).
     */
    #[ORM\ManyToOne(inversedBy: 'missions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TronconDesserte $tronconDesserte = null;

    /**
     * La direction vers laquelle va cette mission (ex: "direction La Defense"), telle
     * qu'affichee sur les quais. Remplace l'ancienne table "sens" (Nord/Sud), qui ne
     * suffisait pas pour les lignes a embranchements (plusieurs terminus possibles).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Direction $direction = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(?int $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getTronconDesserte(): ?TronconDesserte
    {
        return $this->tronconDesserte;
    }

    public function setTronconDesserte(?TronconDesserte $tronconDesserte): static
    {
        $this->tronconDesserte = $tronconDesserte;

        return $this;
    }

    public function getDirection(): ?Direction
    {
        return $this->direction;
    }

    public function setDirection(?Direction $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getTroncon(): ?Troncon
    {
        return $this->tronconDesserte?->getTroncon();
    }

    public function getDepart(): ?Desserte
    {
        return $this->tronconDesserte?->getDesserte();
    }

    public function getArrivee(): ?Desserte
    {
        return $this->getTroncon()?->getDesserteForRole('Arrivée', $this->getDepart());
    }
}
