<?php

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plan de secteur IDFM (dataset "plans-de-secteur") : carte schematique papier couvrant une
 * portion du reseau (un "secteur"), en general a cheval sur peu de communes. Une Station peut
 * apparaitre sur un seul Plan ; un Plan regroupe plusieurs Station (voir Station::$plan).
 * Le PDF officiel n'est pas heberge par ce site (voir urlPdf) : trop volumineux pour
 * l'hebergement mutualise et deja servi de facon fiable par IDFM.
 */
#[ORM\Entity(repositoryClass: PlanRepository::class)]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Identifiant du secteur dans le referentiel IDFM (ex: "3", "24_1" pour un secteur scinde
     * en plusieurs feuillets). Sert de cle d'import rejouable.
     */
    #[ORM\Column(length: 10, unique: true)]
    private ?string $numero = null;

    #[ORM\Column(length: 150)]
    private ?string $secteur = null;

    /**
     * Liste de codes departement separes par des virgules, telle que fournie par IDFM (ex:
     * "92,78,91"). Un departement peut etre couvert par plusieurs Plan (secteurs suburbains
     * scindes), donc ce champ seul ne suffit pas a retrouver le Plan d'une Station.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $departements = null;

    #[ORM\Column(length: 500)]
    private ?string $urlPdf = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $urlFiche = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $tailleFichierMo = null;

    /**
     * Date de publication telle que fournie par IDFM (granularite mois, ex: "2026-01") : pas
     * une date complete, stockee telle quelle.
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $datePublication = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $format = null;

    /**
     * @var Collection<int, Station>
     */
    #[ORM\OneToMany(targetEntity: Station::class, mappedBy: 'plan')]
    private Collection $stations;

    public function __construct()
    {
        $this->stations = new ArrayCollection();
    }

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

    public function getSecteur(): ?string
    {
        return $this->secteur;
    }

    public function setSecteur(string $secteur): static
    {
        $this->secteur = $secteur;

        return $this;
    }

    public function getDepartements(): ?string
    {
        return $this->departements;
    }

    public function setDepartements(?string $departements): static
    {
        $this->departements = $departements;

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

    /**
     * @return Collection<int, Station>
     */
    public function getStations(): Collection
    {
        return $this->stations;
    }

    public function addStation(Station $station): static
    {
        if (!$this->stations->contains($station)) {
            $this->stations->add($station);
            $station->setPlan($this);
        }

        return $this;
    }

    public function removeStation(Station $station): static
    {
        if ($this->stations->removeElement($station)) {
            if ($station->getPlan() === $this) {
                $station->setPlan(null);
            }
        }

        return $this;
    }
}
