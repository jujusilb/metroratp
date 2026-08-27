<?php

namespace App\Entity;

use App\Repository\DesserteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DesserteRepository::class)]
class Desserte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, PeriodeOuverture>
     */
    #[ORM\OneToMany(targetEntity: PeriodeOuverture::class, mappedBy: 'desserte')]
    private Collection $periodesOuverture;

    #[ORM\ManyToOne(inversedBy: 'dessertes')]
    private ?Station $station = null;

    #[ORM\ManyToOne(inversedBy: 'dessertes')]
    private ?Ligne $ligne = null;

    #[ORM\ManyToOne(inversedBy: 'dessertes')]
    private ?StyleStation $styleStation = null;

    /**
     * Style de la signalisation du nom de station sur le quai (ex: police Parisine sur plaque
     * emaillee, nom incorpore dans la ceramique murale style CMP entre-deux-guerres...) - sur
     * Desserte (pas Station), meme principe que StyleStation ci-dessus : une station a plusieurs
     * lignes peut avoir des quais renoves a des epoques differentes avec un lettrage different.
     */
    #[ORM\ManyToOne(inversedBy: 'dessertes')]
    private ?StyleEcriture $styleEcriture = null;

    /**
     * Accessibilite/signaletique officielles IDFM par couple (Station, Ligne) - dataset
     * "sdap-arrets-associes" (route_id -> Ligne::codeExterne, stop_id -> ArRId -> relations.csv ->
     * ZdCId -> Station::codeExterne). Sur Desserte (pas Station) : depend du materiel roulant/du
     * service de CETTE ligne precise a cet arret, pas du lieu en general - un meme arret de bus
     * physique peut etre accessible pour une ligne (bus a plancher bas) et pas une autre. Voir
     * app:importer-accessibilite-dessertes. Null = non trouve dans le dataset source, pas
     * "non accessible". Nommee estAccessible (pas accessible) : ACCESSIBLE est un mot reserve
     * MariaDB, deja rencontre sur ArretTransporteur - meme plantage SQL a l'exacte meme etape la
     * premiere fois.
     */
    #[ORM\Column(nullable: true)]
    private ?bool $estAccessible = null;

    #[ORM\Column(nullable: true)]
    private ?bool $signalisationSonore = null;

    #[ORM\Column(nullable: true)]
    private ?bool $signalisationVisuelle = null;

    /**
     * Climatisation du materiel roulant de CETTE ligne a cet arret - meme dataset/rattachement que
     * estAccessible ci-dessus (sdap-arrets-associes, champ Extensions.ServiceFacilitySet.ClimateControlList),
     * stockee directement en libelle francais lisible ('Climatise'/'Non climatise'/'Autre') plutot
     * que la valeur GTFS brute. Null = non trouve ou valeur 'unknown' dans le dataset source.
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $climatisation = null;

    /**
     * Date d'installation des portes palieres sur ce quai precis, pour cette Ligne precise -
     * contrairement a Ligne::dateAutomatisationTotale (conduite sans conducteur, propriete de
     * toute la ligne), les portes palieres s'installent quai par quai et un deploiement peut
     * rester partiel pendant des annees (ex. Ligne 13 : 13 stations sur 32 equipees entre 2008 et
     * 2012, sans rapport avec son projet d'automatisation vote en 2022, encore non realise). Null
     * = pas de porte paliere installee (ou non documente) - jamais renseigne pour les Desserte
     * bus/tram, meme principe que StyleStation ci-dessus.
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $datePortePaliere = null;

    /**
     * Mobilier physique de l'arret (banc, abri, poubelle...) releve sur OpenStreetMap - voir
     * EquipementArret. Reference plutot que duplique : quand un meme arret physique dessert
     * plusieurs lignes (cas frequent en bus - un seul poteau/banc pour plusieurs lignes), leurs
     * Desserte partagent le MEME EquipementArret (une seule source de verite, pas de valeurs
     * copiees a tenir a jour a plusieurs endroits). Des que le rapprochement peut cibler une ligne
     * precise (gros pole avec plusieurs abribus distincts, ex. Gare de l'Est), chaque Desserte
     * pointe vers son propre EquipementArret. Voir app:importer-equipements-arrets.
     */
    #[ORM\ManyToOne]
    private ?EquipementArret $equipementArret = null;

    /**
     * @var Collection<int, TronconDesserte>
     */
    #[ORM\OneToMany(targetEntity: TronconDesserte::class, mappedBy: 'desserte')]
    private Collection $tronconDessertes;

    /**
     * @var Collection<int, Correspondance>
     */
    #[ORM\OneToMany(targetEntity: Correspondance::class, mappedBy: 'desserteA')]
    private Collection $correspondancesA;

    /**
     * @var Collection<int, Correspondance>
     */
    #[ORM\OneToMany(targetEntity: Correspondance::class, mappedBy: 'desserteB')]
    private Collection $correspondancesB;

    public function __construct()
    {
        $this->tronconDessertes = new ArrayCollection();
        $this->periodesOuverture = new ArrayCollection();
        $this->correspondancesA = new ArrayCollection();
        $this->correspondancesB = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getStation(): ?Station { return $this->station; }
    public function setStation(?Station $station): static { $this->station = $station; return $this; }

    public function getLigne(): ?Ligne { return $this->ligne; }
    public function setLigne(?Ligne $ligne): static { $this->ligne = $ligne; return $this; }

    public function getStyleStation(): ?StyleStation { return $this->styleStation; }
    public function setStyleStation(?StyleStation $styleStation): static { $this->styleStation = $styleStation; return $this; }

    public function getStyleEcriture(): ?StyleEcriture { return $this->styleEcriture; }
    public function setStyleEcriture(?StyleEcriture $styleEcriture): static { $this->styleEcriture = $styleEcriture; return $this; }

    public function isEstAccessible(): ?bool { return $this->estAccessible; }
    public function setEstAccessible(?bool $estAccessible): static { $this->estAccessible = $estAccessible; return $this; }

    public function isSignalisationSonore(): ?bool { return $this->signalisationSonore; }
    public function setSignalisationSonore(?bool $signalisationSonore): static { $this->signalisationSonore = $signalisationSonore; return $this; }

    public function isSignalisationVisuelle(): ?bool { return $this->signalisationVisuelle; }
    public function setSignalisationVisuelle(?bool $signalisationVisuelle): static { $this->signalisationVisuelle = $signalisationVisuelle; return $this; }

    public function getClimatisation(): ?string { return $this->climatisation; }
    public function setClimatisation(?string $climatisation): static { $this->climatisation = $climatisation; return $this; }

    public function getDatePortePaliere(): ?\DateTime { return $this->datePortePaliere; }
    public function setDatePortePaliere(?\DateTime $datePortePaliere): static { $this->datePortePaliere = $datePortePaliere; return $this; }

    public function getEquipementArret(): ?EquipementArret { return $this->equipementArret; }
    public function setEquipementArret(?EquipementArret $equipementArret): static { $this->equipementArret = $equipementArret; return $this; }

    /**
     * @return Collection<int, TronconDesserte>
     */
    public function getTronconDessertes(): Collection
    {
        return $this->tronconDessertes;
    }

    /**
     * @return Collection<int, PeriodeOuverture>
     */
    public function getPeriodesOuverture(): Collection
    {
        return $this->periodesOuverture;
    }

    /**
     * Les periodes d'ouverture triees chronologiquement (par ordre).
     *
     * @return PeriodeOuverture[]
     */
    public function getPeriodesOuvertureOrdonnees(): array
    {
        $periodes = $this->periodesOuverture->toArray();
        usort($periodes, static fn (PeriodeOuverture $a, PeriodeOuverture $b): int => $a->getOrdre() <=> $b->getOrdre());

        return $periodes;
    }

    /**
     * Date de la toute premiere ouverture connue (periode d'ordre le plus bas). Null si
     * aucune periode n'est enregistree ou si la date n'est pas connue.
     */
    public function getPremiereOuverture(): ?\DateTime
    {
        $periodes = $this->getPeriodesOuvertureOrdonnees();

        return ($periodes[0] ?? null)?->getOuverture();
    }

    /**
     * La derniere periode d'ouverture enregistree (la plus recente par ordre), qui determine
     * si la station est actuellement ouverte ou fermee.
     */
    public function getDernierePeriode(): ?PeriodeOuverture
    {
        $periodes = $this->getPeriodesOuvertureOrdonnees();

        return $periodes[count($periodes) - 1] ?? null;
    }

    /**
     * Vraie si la station est actuellement ouverte : soit aucune periode n'est enregistree
     * (statut inconnu, on ne suppose pas qu'elle est fermee), soit la derniere periode n'a
     * pas de date de fermeture.
     */
    public function isOuverte(): bool
    {
        $derniere = $this->getDernierePeriode();

        return null === $derniere || null === $derniere->getFermeture();
    }

    /**
     * Nombre de troncons distincts touchant cette desserte. Une desserte terminus
     * (bout de ligne ou de branche) n'en touche qu'un seul ; une desserte "au milieu"
     * de la ligne en touche deux (un de chaque cote).
     */
    public function getNombreTronconsDistincts(): int
    {
        $ids = [];
        foreach ($this->tronconDessertes as $tronconDesserte) {
            $tronconId = $tronconDesserte->getTroncon()?->getId();
            if (null !== $tronconId) {
                $ids[$tronconId] = true;
            }
        }

        return count($ids);
    }

    /**
     * Toutes les correspondances de cette desserte, quel que soit son role (A ou B) dans la
     * paire, triees par distance croissante (les correspondances sans distance connue en
     * dernier).
     *
     * @return Correspondance[]
     */
    public function getCorrespondances(): array
    {
        $correspondances = [...$this->correspondancesA, ...$this->correspondancesB];

        usort(
            $correspondances,
            static fn (Correspondance $a, Correspondance $b): int => ($a->getDistance() ?? PHP_INT_MAX) <=> ($b->getDistance() ?? PHP_INT_MAX)
        );

        return $correspondances;
    }

    /**
     * L'autre desserte d'une correspondance donnee (celle qui n'est pas $this).
     */
    public function getAutreDesserte(Correspondance $correspondance): ?Desserte
    {
        return $correspondance->getDesserteA() === $this ? $correspondance->getDesserteB() : $correspondance->getDesserteA();
    }

    /**
     * Les troncons pour lesquels cette desserte joue le role "Depart", en excluant
     * eventuellement celui par lequel on est arrive (pour ne pas rebrousser chemin
     * lors d'un parcours de graphe).
     *
     * @return Troncon[]
     */
    public function getTronconsDepart(?Troncon $excluding = null): array
    {
        $troncons = [];
        foreach ($this->tronconDessertes as $tronconDesserte) {
            if ('Départ' !== $tronconDesserte->getTypeDesserte()?->getLabel()) {
                continue;
            }
            $troncon = $tronconDesserte->getTroncon();
            if (null === $troncon || $troncon === $excluding) {
                continue;
            }
            $troncons[] = $troncon;
        }

        return $troncons;
    }
}
