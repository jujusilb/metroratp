<?php

namespace App\Entity;

use App\Repository\DocumentLigneRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fiche horaire ou plan PDF officiel d'une Ligne (dataset IDFM "fiches-horaires-et-plans", 4507
 * documents - une Ligne peut avoir plusieurs fiches, ex: une par sens/variante). Jamais heberge
 * par ce site (voir url), meme choix que Plan::urlPdf.
 */
#[ORM\Entity(repositoryClass: DocumentLigneRepository::class)]
class DocumentLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ligne $ligne = null;

    /**
     * "HORAIRE" ou "PLAN" cote source - stocke tel quel plutot que mappe sur une enumeration.
     */
    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $url = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }
}
