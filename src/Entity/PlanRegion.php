<?php

namespace App\Entity;

use App\Repository\PlanRegionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plan regional IDFM (dataset "plans-region") : grande carte d'ensemble du reseau (Metro, RER,
 * reseau de Nuit, plans PMR/facile a lire...), par opposition a Plan (un Plan de secteur ne
 * couvre qu'une portion du reseau). 19 plans. Meme choix que Plan::urlPdf : jamais heberge par ce
 * site, lien vers le PDF officiel IDFM.
 */
#[ORM\Entity(repositoryClass: PlanRegionRepository::class)]
class PlanRegion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, unique: true)]
    private ?string $numero = null;

    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\Column(length: 150)]
    private ?string $label = null;

    #[ORM\Column(length: 500)]
    private ?string $urlPdf = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $urlFiche = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $tailleFichierMo = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $datePublication = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $format = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getUrlPdf(): ?string
    {
        return $this->urlPdf;
    }

    public function setUrlPdf(string $urlPdf): static
    {
        $this->urlPdf = $urlPdf;

        return $this;
    }

    public function getUrlFiche(): ?string
    {
        return $this->urlFiche;
    }

    public function setUrlFiche(?string $urlFiche): static
    {
        $this->urlFiche = $urlFiche;

        return $this;
    }

    public function getTailleFichierMo(): ?float
    {
        return $this->tailleFichierMo;
    }

    public function setTailleFichierMo(?float $tailleFichierMo): static
    {
        $this->tailleFichierMo = $tailleFichierMo;

        return $this;
    }

    public function getDatePublication(): ?string
    {
        return $this->datePublication;
    }

    public function setDatePublication(?string $datePublication): static
    {
        $this->datePublication = $datePublication;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): static
    {
        $this->format = $format;

        return $this;
    }
}
