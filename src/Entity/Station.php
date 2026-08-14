<?php

namespace App\Entity;

use App\Repository\StationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StationRepository::class)]
class Station
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $label = null;

    /**
     * Commune de la station (ZdCTown du referentiel IDFM). Permet de distinguer deux stations
     * au nom identique dans des communes differentes (ex: l'arret "Mairie" existe dans des
     * dizaines de communes sans etre le meme endroit).
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    /**
     * Identifiant "zone de correspondance" IDFM (ZdCId, referentiel-arret-tc-idf). Permet de
     * relier une station a un lieu reel precis meme quand son nom seul est ambigu (ex: de
     * nombreuses communes ont un arret de bus "Mairie" ou "Eglise" qui ne sont pas le meme
     * endroit) : indispensable pour un import fiable et rejouable au-dela du metro/RER.
     */
    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $codeExterne = null;

    /**
     * Position sur le plan schematique officiel du reseau (coordonnees Ile-de-France Mobilites,
     * pas des coordonnees geographiques) : voir la commande app:importer-coordonnees-schema.
     * Null si la station n'a pas ete trouvee dans la source (ex: extension recente).
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $schemaX = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $schemaY = null;

    /**
     * Coordonnees geographiques reelles (WGS84, degres decimaux), depuis stops.txt (GTFS IDFM,
     * location_type=1, memes lignes que le codeExterne/ZdC) : voir la commande
     * app:importer-coordonnees-geographiques. Contrairement a schemaX/Y (plan deforme,
     * metro/RER/tram seulement), couvre tous les modes sur toute l'Ile-de-France - c'est ce que
     * la carte du calculateur de trajet utilise pour un fond de carte reel (Leaflet/OSM).
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    /**
     * Plan de secteur (carte schematique papier IDFM) sur lequel cette Station apparait. Une
     * Station n'appartient qu'a un seul Plan (voir Plan::$stations pour la relation inverse).
     * Assignation automatique impossible en general : la plupart des departements suburbains
     * sont couverts par plusieurs Plan distincts (voir app:importer-plans-secteur) - a completer
     * manuellement ici au besoin.
     */
    #[ORM\ManyToOne(inversedBy: 'stations')]
    private ?Plan $plan = null;

    /**
     * @var Collection<int, Sortie>
     */
    #[ORM\OneToMany(targetEntity: Sortie::class, mappedBy: 'station')]
    private Collection $sorties;

    /**
     * @var Collection<int, Desserte>
     */
    #[ORM\OneToMany(targetEntity: Desserte::class, mappedBy: 'station')]
    private Collection $dessertes;

    public function __construct()
    {
        $this->sorties = new ArrayCollection();
        $this->dessertes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCodeExterne(): ?string
    {
        return $this->codeExterne;
    }

    public function setCodeExterne(?string $codeExterne): static
    {
        $this->codeExterne = $codeExterne;

        return $this;
    }

    public function getSchemaX(): ?float
    {
        return $this->schemaX;
    }

    public function setSchemaX(?float $schemaX): static
    {
        $this->schemaX = $schemaX;

        return $this;
    }

    public function getSchemaY(): ?float
    {
        return $this->schemaY;
    }

    public function setSchemaY(?float $schemaY): static
    {
        $this->schemaY = $schemaY;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    /**
     * @return Collection<int, Sortie>
     */
    public function getSorties(): Collection
    {
        return $this->sorties;
    }

    public function addSorty(Sortie $sorty): static
    {
        if (!$this->sorties->contains($sorty)) {
            $this->sorties->add($sorty);
            $sorty->setStation($this);
        }

        return $this;
    }

    public function removeSorty(Sortie $sorty): static
    {
        if ($this->sorties->removeElement($sorty)) {
            // set the owning side to null (unless already changed)
            if ($sorty->getStation() === $this) {
                $sorty->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Desserte>
     */
    public function getDessertes(): Collection
    {
        return $this->dessertes;
    }

    public function addDesserte(Desserte $desserte): static
    {
        if (!$this->dessertes->contains($desserte)) {
            $this->dessertes->add($desserte);
            $desserte->setStation($this);
        }

        return $this;
    }

    public function removeDesserte(Desserte $desserte): static
    {
        if ($this->dessertes->removeElement($desserte)) {
            // set the owning side to null (unless already changed)
            if ($desserte->getStation() === $this) {
                $desserte->setStation(null);
            }
        }

        return $this;
    }
}
