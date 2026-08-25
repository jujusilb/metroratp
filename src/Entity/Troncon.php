<?php

namespace App\Entity;

use App\Repository\TronconRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TronconRepository::class)]
class Troncon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Distance reelle (en metres) entre les deux dessertes de ce troncon, calculee a partir
     * des tracés GTFS IDFM (shapes.txt / stop_times.txt, voir commande
     * app:importer-distances-troncon). Fixe quel que soit le materiel qui circule dessus,
     * contrairement a dureeReelleSecondes.
     */
    #[ORM\Column(nullable: true)]
    private ?float $distance = null;

    /**
     * Duree reelle moyenne (en secondes) entre les deux dessertes de ce troncon, calculee a
     * partir des horaires theoriques GTFS IDFM (voir commande app:importer-durees-troncon).
     * Null si aucune correspondance n'a ete trouvee : TrajetFinder retombe alors sur son
     * poids fixe par defaut.
     */
    #[ORM\Column(nullable: true)]
    private ?int $dureeReelleSecondes = null;

    #[ORM\ManyToOne(inversedBy: 'troncons')]
    private ?TypeTroncon $typeTroncon = null;

    /**
     * Etiquette manuelle, posee uniquement sur le Troncon qui referme un vrai maillage (plusieurs
     * itineraires physiques distincts entre deux memes points, ex. RER D entre
     * Villeneuve-Saint-Georges et Juvisy/Viry-Châtillon) - decrit la voie alternative empruntee,
     * affichee dans le badge "rejoint" de Ligne::getParcoursSegments() a la place du texte
     * generique. Null partout ailleurs (embranchements simples en arbre, qui n'ont besoin
     * d'aucune explication).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $varianteMaillage = null;

    /**
     * @var Collection<int, TronconDesserte>
     */
    #[ORM\OneToMany(targetEntity: TronconDesserte::class, mappedBy: 'troncon')]
    private Collection $tronconDessertes;

    public function __construct()
    {
        $this->tronconDessertes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(?float $distance): static
    {
        $this->distance = $distance;

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

    public function getTypeTroncon(): ?TypeTroncon
    {
        return $this->typeTroncon;
    }

    public function setTypeTroncon(?TypeTroncon $typeTroncon): static
    {
        $this->typeTroncon = $typeTroncon;

        return $this;
    }

    public function getVarianteMaillage(): ?string
    {
        return $this->varianteMaillage;
    }

    public function setVarianteMaillage(?string $varianteMaillage): static
    {
        $this->varianteMaillage = $varianteMaillage;

        return $this;
    }

    /**
     * @return Collection<int, TronconDesserte>
     */
    public function getTronconDessertes(): Collection
    {
        return $this->tronconDessertes;
    }

    /**
     * La desserte jouant le role donne ("Depart" ou "Arrivee") pour ce troncon,
     * eventuellement en excluant une desserte precise (utile pour trouver "l'autre
     * bout" du troncon quand on connait deja un cote).
     */
    public function getDesserteForRole(string $roleLabel, ?Desserte $excluding = null): ?Desserte
    {
        foreach ($this->tronconDessertes as $tronconDesserte) {
            if ($tronconDesserte->getTypeDesserte()?->getLabel() !== $roleLabel) {
                continue;
            }
            $desserte = $tronconDesserte->getDesserte();
            if (null !== $excluding && $desserte === $excluding) {
                continue;
            }

            return $desserte;
        }

        return null;
    }

    /**
     * Les (jusqu'a) deux sens de circulation possibles sur ce troncon (aller et retour) : pour
     * chacun, le depart, l'arrivee, et la direction terminus deduite d'une mission existante
     * empruntant ce troncon dans ce sens (s'il y en a une).
     *
     * @return array<int, array{depart: ?Desserte, arrivee: ?Desserte, direction: ?Desserte}>
     */
    public function getSensCirculation(): array
    {
        $sens = [];

        foreach ($this->tronconDessertes as $tronconDesserte) {
            if ('Départ' !== $tronconDesserte->getTypeDesserte()?->getLabel()) {
                continue;
            }

            $depart = $tronconDesserte->getDesserte();
            $direction = null;
            foreach ($tronconDesserte->getMissions() as $mission) {
                $direction = $mission->getDirection();
                break;
            }

            $sens[] = [
                'depart' => $depart,
                'arrivee' => $this->getDesserteForRole('Arrivée', $depart),
                'direction' => $direction,
            ];
        }

        return $sens;
    }
}
