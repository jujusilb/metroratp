<?php

namespace App\Entity;

use App\Repository\TacheRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Suivi de travail sur le projet lui-meme (pas une donnee du reseau de transport) : remplace le
 * suivi manuel dans documentation/TODO.md, sujet a des erreurs d'edition (section markdown mal
 * placee, contenu perdu). Reserve a ROLE_ADMIN (voir security.yaml), n'a pas vocation a etre vu
 * par un visiteur normal du site. Le detail narratif (commandes executees, decouvertes,
 * verifications) reste dans documentation/commande.md, plus adapte en texte libre qu'en base.
 */
#[ORM\Entity(repositoryClass: TacheRepository::class)]
class Tache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $datetimeCreation = null;

    #[ORM\ManyToOne(inversedBy: 'taches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StatutTache $statut = null;

    /**
     * Derniere fois que cette Tache a ete travaillee (mise a jour manuelle, pas automatique) -
     * distinct de datetimeCreation (jamais modifiee) et datetimeAchevement (mise une seule fois).
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $datetimeAction = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $datetimeAchevement = null;

    /**
     * @var Collection<int, Etape>
     */
    #[ORM\OneToMany(targetEntity: Etape::class, mappedBy: 'tache', orphanRemoval: true)]
    private Collection $etapes;

    public function __construct()
    {
        $this->datetimeCreation = new \DateTime();
        $this->etapes = new ArrayCollection();
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

    public function getDatetimeCreation(): ?\DateTime
    {
        return $this->datetimeCreation;
    }

    public function setDatetimeCreation(\DateTime $datetimeCreation): static
    {
        $this->datetimeCreation = $datetimeCreation;

        return $this;
    }

    public function getStatut(): ?StatutTache
    {
        return $this->statut;
    }

    public function setStatut(?StatutTache $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDatetimeAction(): ?\DateTime
    {
        return $this->datetimeAction;
    }

    public function setDatetimeAction(?\DateTime $datetimeAction): static
    {
        $this->datetimeAction = $datetimeAction;

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

    /**
     * @return Collection<int, Etape>
     */
    public function getEtapes(): Collection
    {
        return $this->etapes;
    }

    public function addEtape(Etape $etape): static
    {
        if (!$this->etapes->contains($etape)) {
            $this->etapes->add($etape);
            $etape->setTache($this);
        }

        return $this;
    }

    public function removeEtape(Etape $etape): static
    {
        if ($this->etapes->removeElement($etape)) {
            if ($etape->getTache() === $this) {
                // Pas de setTache(null) : Etape::tache est NOT NULL (JoinColumn nullable: false),
                // orphanRemoval s'occupe de la suppression reelle en base.
            }
        }

        return $this;
    }
}
