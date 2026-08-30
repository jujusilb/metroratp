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
     * Commune reelle (referentiel geo.api.gouv.fr, voir Ville), rattachee par correspondance de
     * nom depuis le champ ville ci-dessus (voir app:importer-villes) - donne acces a la frontiere
     * geographique reelle pour l'affichage carte, contrairement a ville qui reste du texte libre.
     * Reste null si aucune correspondance fiable (ex: commune hors Ile-de-France, perimetre des
     * donnees de frontiere - voir documentation/TODO.md).
     */
    #[ORM\ManyToOne(inversedBy: 'stations')]
    private ?Ville $villeRef = null;

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
     * Pole d'echange (hub multimodal officiel IDFM) auquel cette Station appartient, le cas
     * echeant (voir PoleEchange::$stations et app:importer-poles-echange).
     */
    #[ORM\ManyToOne(inversedBy: 'stations')]
    private ?PoleEchange $poleEchange = null;

    /**
     * Niveau d'accessibilite PMR de la gare (dataset IDFM "accessibilite-en-gare", ~459 gares
     * seulement - train/RER/metro principalement, pas tous les arrets de bus) : voir
     * app:importer-accessibilite-gares. Valeur en texte libre cote source (4 niveaux distincts
     * observes, pas une enumeration stricte garantie stable) : stockee telle quelle plutot que
     * mappee sur un type dedie.
     */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $accessibilitePmr = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $accessibilitePmrCommentaire = null;

    /**
     * Zone tarifaire Ile-de-France Mobilites (1 a 5, referentiel officiel "arrets-transporteur",
     * ArTFareZone) - propriete du lieu, pas de la ligne : ne varie pas selon la ligne empruntee
     * depuis cette Station.
     */
    #[ORM\Column(nullable: true)]
    private ?int $zoneTarifaire = null;

    /**
     * Acces rattaches a cette Station - ManyToMany plutot qu'une simple table de jointure "Sortie"
     * (aucune donnee propre, juste 2 cles etrangeres) : cote inverse, voir Acces::stations.
     *
     * @var Collection<int, Acces>
     */
    #[ORM\ManyToMany(targetEntity: Acces::class, mappedBy: 'stations')]
    private Collection $acces;

    /**
     * @var Collection<int, Desserte>
     */
    #[ORM\OneToMany(targetEntity: Desserte::class, mappedBy: 'station')]
    private Collection $dessertes;

    /**
     * Points de vente les plus proches (rattachement par proximite geographique, voir
     * PointDeVente pour les limites de ce rattachement).
     *
     * @var Collection<int, PointDeVente>
     */
    #[ORM\OneToMany(targetEntity: PointDeVente::class, mappedBy: 'station')]
    private Collection $pointsDeVente;

    /**
     * Sanitaires les plus proches (rattachement par proximite geographique, meme limite que
     * PointDeVente : pas d'identifiant officiel dans la donnee source).
     *
     * @var Collection<int, Sanitaire>
     */
    #[ORM\OneToMany(targetEntity: Sanitaire::class, mappedBy: 'station')]
    private Collection $sanitaires;

    /**
     * Defibrillateurs les plus proches (rattachement par proximite geographique, meme limite
     * que PointDeVente/Sanitaire).
     *
     * @var Collection<int, Defibrillateur>
     */
    #[ORM\OneToMany(targetEntity: Defibrillateur::class, mappedBy: 'station')]
    private Collection $defibrillateurs;

    /**
     * Fontaines a eau rattachees a cette Station. Contrairement aux collections ci-dessus, ce
     * rattachement est officiel (derive de FontaineEau::acces via Sortie), pas une approximation
     * geographique - voir app:importer-fontaines-eau.
     *
     * @var Collection<int, FontaineEau>
     */
    #[ORM\OneToMany(targetEntity: FontaineEau::class, mappedBy: 'station')]
    private Collection $fontainesEau;

    /**
     * Sanisettes publiques (Ville de Paris) les plus proches (rattachement par proximite
     * geographique, meme limite que PointDeVente/Sanitaire) - dataset distinct des Sanitaire RATP
     * en station.
     *
     * @var Collection<int, SanisettePublique>
     */
    #[ORM\OneToMany(targetEntity: SanisettePublique::class, mappedBy: 'station')]
    private Collection $sanisettesPubliques;

    /**
     * Lieux remarquables a proximite (voir PointInteret) - un meme lieu peut etre partage par
     * plusieurs Station proches (ex: Forum des Halles pres de Chatelet/Chatelet-Les Halles/Les
     * Halles).
     *
     * @var Collection<int, PointInteret>
     */
    #[ORM\ManyToMany(targetEntity: PointInteret::class, mappedBy: 'stations')]
    private Collection $pointsInteret;

    public function __construct()
    {
        $this->acces = new ArrayCollection();
        $this->dessertes = new ArrayCollection();
        $this->pointsDeVente = new ArrayCollection();
        $this->sanitaires = new ArrayCollection();
        $this->defibrillateurs = new ArrayCollection();
        $this->pointsInteret = new ArrayCollection();
        $this->fontainesEau = new ArrayCollection();
        $this->sanisettesPubliques = new ArrayCollection();
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

    public function getVilleRef(): ?Ville
    {
        return $this->villeRef;
    }

    public function setVilleRef(?Ville $villeRef): static
    {
        $this->villeRef = $villeRef;

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

    public function getPoleEchange(): ?PoleEchange
    {
        return $this->poleEchange;
    }

    public function setPoleEchange(?PoleEchange $poleEchange): static
    {
        $this->poleEchange = $poleEchange;

        return $this;
    }

    public function getAccessibilitePmr(): ?string
    {
        return $this->accessibilitePmr;
    }

    public function setAccessibilitePmr(?string $accessibilitePmr): static
    {
        $this->accessibilitePmr = $accessibilitePmr;

        return $this;
    }

    public function getAccessibilitePmrCommentaire(): ?string
    {
        return $this->accessibilitePmrCommentaire;
    }

    public function setAccessibilitePmrCommentaire(?string $accessibilitePmrCommentaire): static
    {
        $this->accessibilitePmrCommentaire = $accessibilitePmrCommentaire;

        return $this;
    }

    public function getZoneTarifaire(): ?int
    {
        return $this->zoneTarifaire;
    }

    public function setZoneTarifaire(?int $zoneTarifaire): static
    {
        $this->zoneTarifaire = $zoneTarifaire;

        return $this;
    }

    /**
     * @return Collection<int, Acces>
     */
    public function getAcces(): Collection
    {
        return $this->acces;
    }

    public function addAcce(Acces $acce): static
    {
        if (!$this->acces->contains($acce)) {
            $this->acces->add($acce);
            $acce->addStation($this);
        }

        return $this;
    }

    public function removeAcce(Acces $acce): static
    {
        if ($this->acces->removeElement($acce)) {
            $acce->removeStation($this);
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

    /**
     * @return Collection<int, PointDeVente>
     */
    public function getPointsDeVente(): Collection
    {
        return $this->pointsDeVente;
    }

    public function addPointDeVente(PointDeVente $pointDeVente): static
    {
        if (!$this->pointsDeVente->contains($pointDeVente)) {
            $this->pointsDeVente->add($pointDeVente);
            $pointDeVente->setStation($this);
        }

        return $this;
    }

    public function removePointDeVente(PointDeVente $pointDeVente): static
    {
        if ($this->pointsDeVente->removeElement($pointDeVente)) {
            if ($pointDeVente->getStation() === $this) {
                $pointDeVente->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Sanitaire>
     */
    public function getSanitaires(): Collection
    {
        return $this->sanitaires;
    }

    public function addSanitaire(Sanitaire $sanitaire): static
    {
        if (!$this->sanitaires->contains($sanitaire)) {
            $this->sanitaires->add($sanitaire);
            $sanitaire->setStation($this);
        }

        return $this;
    }

    public function removeSanitaire(Sanitaire $sanitaire): static
    {
        if ($this->sanitaires->removeElement($sanitaire)) {
            if ($sanitaire->getStation() === $this) {
                $sanitaire->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Defibrillateur>
     */
    public function getDefibrillateurs(): Collection
    {
        return $this->defibrillateurs;
    }

    public function addDefibrillateur(Defibrillateur $defibrillateur): static
    {
        if (!$this->defibrillateurs->contains($defibrillateur)) {
            $this->defibrillateurs->add($defibrillateur);
            $defibrillateur->setStation($this);
        }

        return $this;
    }

    public function removeDefibrillateur(Defibrillateur $defibrillateur): static
    {
        if ($this->defibrillateurs->removeElement($defibrillateur)) {
            if ($defibrillateur->getStation() === $this) {
                $defibrillateur->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FontaineEau>
     */
    public function getFontainesEau(): Collection
    {
        return $this->fontainesEau;
    }

    public function addFontaineEau(FontaineEau $fontaineEau): static
    {
        if (!$this->fontainesEau->contains($fontaineEau)) {
            $this->fontainesEau->add($fontaineEau);
            $fontaineEau->setStation($this);
        }

        return $this;
    }

    public function removeFontaineEau(FontaineEau $fontaineEau): static
    {
        if ($this->fontainesEau->removeElement($fontaineEau)) {
            if ($fontaineEau->getStation() === $this) {
                $fontaineEau->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SanisettePublique>
     */
    public function getSanisettesPubliques(): Collection
    {
        return $this->sanisettesPubliques;
    }

    public function addSanisettePublique(SanisettePublique $sanisettePublique): static
    {
        if (!$this->sanisettesPubliques->contains($sanisettePublique)) {
            $this->sanisettesPubliques->add($sanisettePublique);
            $sanisettePublique->setStation($this);
        }

        return $this;
    }

    public function removeSanisettePublique(SanisettePublique $sanisettePublique): static
    {
        if ($this->sanisettesPubliques->removeElement($sanisettePublique)) {
            if ($sanisettePublique->getStation() === $this) {
                $sanisettePublique->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PointInteret>
     */
    public function getPointsInteret(): Collection
    {
        return $this->pointsInteret;
    }

    /**
     * Active si au moins une Desserte existe et qu'au moins une d'entre elles est elle-meme
     * active (Desserte::estActive(), vide de Raison). Une Station sans aucune Desserte est donc
     * inactive par construction (rien ne la dessert), sans avoir besoin de sa propre Raison - voir
     * TODO.md "stations fantomes" pour la remarque utilisateur ayant motive ce calcul plutot qu'un
     * Station::raisons independant (desormais supprime, migration Version20260829110000).
     */
    public function estActive(): bool
    {
        foreach ($this->dessertes as $desserte) {
            if ($desserte->estActive()) {
                return true;
            }
        }

        return false;
    }
}
