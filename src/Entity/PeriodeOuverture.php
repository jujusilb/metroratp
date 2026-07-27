<?php

namespace App\Entity;

use App\Repository\PeriodeOuvertureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une periode continue pendant laquelle une desserte a ete ouverte au public. Une station
 * peut avoir plusieurs periodes (ex: fermeture puis reouverture pendant la Seconde Guerre
 * mondiale), ou n'avoir jamais rouvert (station fantome, "fermeture" restant NULL indique
 * alors que la periode courante est toujours active). Le champ "ordre" fixe la chronologie
 * sans risque d'erreur meme si les dates exactes sont inconnues.
 */
#[ORM\Entity(repositoryClass: PeriodeOuvertureRepository::class)]
class PeriodeOuverture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'periodesOuverture')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Desserte $desserte = null;

    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $ouverture = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $fermeture = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getOuverture(): ?\DateTime
    {
        return $this->ouverture;
    }

    public function setOuverture(?\DateTime $ouverture): static
    {
        $this->ouverture = $ouverture;

        return $this;
    }

    public function getFermeture(): ?\DateTime
    {
        return $this->fermeture;
    }

    public function setFermeture(?\DateTime $fermeture): static
    {
        $this->fermeture = $fermeture;

        return $this;
    }
}
