<?php

namespace App\Entity;

use App\Repository\EtapeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une etape (a gros grain : conception, implementation, tests, deploiement, verification prod...)
 * d'une Tache. Voir Tache pour le contexte general (suivi de projet, reserve a ROLE_ADMIN).
 */
#[ORM\Entity(repositoryClass: EtapeRepository::class)]
class Etape
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'etapes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tache $tache = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $datetimeCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $datetimeAchevement = null;

    public function __construct()
    {
        $this->datetimeCreation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTache(): ?Tache
    {
        return $this->tache;
    }

    public function setTache(?Tache $tache): static
    {
        $this->tache = $tache;

        return $this;
    }

    public function getDatetimeCreation(): ?\DateTime
    {
        return $this->datetimeCreation;
    }

    public function setDatetimeCreation(\DateTime $datetimeCreation): static
    {
        $this->datetimeCreation = $datetimeCreation;

        return $this;
    }

    public function getDatetimeAchevement(): ?\DateTime
    {
        return $this->datetimeAchevement;
    }

    public function setDatetimeAchevement(?\DateTime $datetimeAchevement): static
    {
        $this->datetimeAchevement = $datetimeAchevement;

        return $this;
    }
}
