<?php

namespace App\Entity;

use App\Repository\CorrespondanceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Une correspondance entre deux dessertes (deux quais), avec la distance de marche et si
 * elle se fait "en zone" (sans repasser de controle d'acces) ou non. La paire est toujours
 * stockee dans un ordre canonique (desserteA.id < desserteB.id, impose par un lifecycle
 * callback + une contrainte CHECK en base) : une correspondance A<->B n'existe donc qu'une
 * seule fois, jamais dupliquee en (A,B) et (B,A).
 *
 * directionA/directionB sont optionnelles : elles permettent de preciser, pour les stations
 * ou la distance de marche varie selon le quai (ex: Chatelet 4<->14), quelle direction sur
 * chaque ligne est concernee. Plusieurs correspondances peuvent exister pour la meme paire
 * de dessertes, une par combinaison de directions.
 */
#[ORM\Entity(repositoryClass: CorrespondanceRepository::class)]
#[ORM\UniqueConstraint(name: 'correspondance_unique_paire', columns: ['desserte_a_id', 'desserte_b_id', 'direction_a_id', 'direction_b_id'])]
#[ORM\HasLifecycleCallbacks]
#[Assert\Callback('validerDessertesDistinctes')]
#[Assert\Callback('validerDirectionsCoherentes')]
class Correspondance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'correspondancesA')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Desserte $desserteA = null;

    #[ORM\ManyToOne(inversedBy: 'correspondancesB')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Desserte $desserteB = null;

    /**
     * Direction empruntee sur la ligne de desserteA, si elle doit etre precisee (sinon la
     * correspondance vaut pour toutes les directions de cette ligne a cet endroit).
     */
    #[ORM\ManyToOne]
    private ?Direction $directionA = null;

    /**
     * Direction empruntee sur la ligne de desserteB, si elle doit etre precisee.
     */
    #[ORM\ManyToOne]
    private ?Direction $directionB = null;

    /**
     * Distance de marche en metres. Nullable : on prefere laisser vide plutot qu'inventer
     * une valeur quand la distance reelle n'est pas connue.
     */
    #[ORM\Column(nullable: true)]
    private ?int $distance = null;

    #[ORM\Column]
    private bool $inZone = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDesserteA(): ?Desserte
    {
        return $this->desserteA;
    }

    public function setDesserteA(?Desserte $desserteA): static
    {
        $this->desserteA = $desserteA;

        return $this;
    }

    public function getDesserteB(): ?Desserte
    {
        return $this->desserteB;
    }

    public function setDesserteB(?Desserte $desserteB): static
    {
        $this->desserteB = $desserteB;

        return $this;
    }

    public function getDirectionA(): ?Direction
    {
        return $this->directionA;
    }

    public function setDirectionA(?Direction $directionA): static
    {
        $this->directionA = $directionA;

        return $this;
    }

    public function getDirectionB(): ?Direction
    {
        return $this->directionB;
    }

    public function setDirectionB(?Direction $directionB): static
    {
        $this->directionB = $directionB;

        return $this;
    }

    public function getDistance(): ?int
    {
        return $this->distance;
    }

    public function setDistance(?int $distance): static
    {
        $this->distance = $distance;

        return $this;
    }

    public function isInZone(): bool
    {
        return $this->inZone;
    }

    public function setInZone(bool $inZone): static
    {
        $this->inZone = $inZone;

        return $this;
    }

    /**
     * Temps de correspondance estime, en minutes, a partir de la distance (si connue), avec
     * une vitesse de marche moyenne en couloir de metro (~0.9 m/s, plus lente qu'une marche
     * exterieure car elle tient compte des escaliers/couloirs). Reste une ESTIMATION, pas
     * une donnee officielle RATP.
     */
    public function getTempsEstimeMinutes(): ?float
    {
        if (null === $this->distance) {
            return null;
        }

        return round($this->distance / 0.9 / 60, 1);
    }

    /**
     * Message d'erreur propre cote formulaire plutot que de laisser remonter l'exception SQL
     * brute du CHECK "desserte_a_id < desserte_b_id" (qui echoue aussi quand a = b, puisque
     * a < a est toujours faux).
     */
    public function validerDessertesDistinctes(ExecutionContextInterface $context): void
    {
        if (null !== $this->desserteA && $this->desserteA === $this->desserteB) {
            $context->buildViolation('Les deux dessertes doivent être différentes.')
                ->atPath('desserteB')
                ->addViolation();
        }
    }

    /**
     * Une direction precisee doit appartenir a la meme ligne que la desserte correspondante,
     * sinon la combinaison n'a pas de sens (ex: direction de la ligne 4 associee a une
     * desserte de la ligne 14).
     */
    public function validerDirectionsCoherentes(ExecutionContextInterface $context): void
    {
        if (null !== $this->directionA && null !== $this->desserteA
            && $this->directionA->getLigne() !== $this->desserteA->getLigne()) {
            $context->buildViolation('La direction choisie ne correspond pas à la ligne de la première desserte.')
                ->atPath('directionA')
                ->addViolation();
        }

        if (null !== $this->directionB && null !== $this->desserteB
            && $this->directionB->getLigne() !== $this->desserteB->getLigne()) {
            $context->buildViolation('La direction choisie ne correspond pas à la ligne de la seconde desserte.')
                ->atPath('directionB')
                ->addViolation();
        }
    }

    /**
     * Impose l'ordre canonique desserteA.id < desserteB.id avant chaque ecriture (en echangeant
     * aussi les directions associees, pour rester coherent), pour que la contrainte d'unicite
     * (et le CHECK en base) empechent vraiment les doublons (A,B) et (B,A), quel que soit
     * l'ordre choisi par l'utilisateur dans le formulaire.
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function normaliserOrdre(): void
    {
        if (null === $this->desserteA || null === $this->desserteB) {
            return;
        }

        if ($this->desserteA->getId() > $this->desserteB->getId()) {
            [$this->desserteA, $this->desserteB] = [$this->desserteB, $this->desserteA];
            [$this->directionA, $this->directionB] = [$this->directionB, $this->directionA];
        }
    }
}
