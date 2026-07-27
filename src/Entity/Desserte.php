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
     * @var Collection<int, TronconDesserte>
     */
    #[ORM\OneToMany(targetEntity: TronconDesserte::class, mappedBy: 'desserte')]
    private Collection $tronconDessertes;

    public function __construct()
    {
        $this->tronconDessertes = new ArrayCollection();
        $this->periodesOuverture = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getStation(): ?Station { return $this->station; }
    public function setStation(?Station $station): static { $this->station = $station; return $this; }

    public function getLigne(): ?Ligne { return $this->ligne; }
    public function setLigne(?Ligne $ligne): static { $this->ligne = $ligne; return $this; }

    public function getStyleStation(): ?StyleStation { return $this->styleStation; }
    public function setStyleStation(?StyleStation $styleStation): static { $this->styleStation = $styleStation; return $this; }

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

        return $periodes[0]?->getOuverture();
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
