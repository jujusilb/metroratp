<?php

namespace App\Entity;

use App\Repository\RaisonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catalogue des raisons pour lesquelles une Desserte est consideree inactive (aucun service
 * exploitable aujourd'hui). Pas de champ "actif" separe sur Desserte : la seule presence d'au
 * moins une Raison liee signifie "inactive" (absence = active), pour eviter une donnee redondante
 * qui pourrait se desynchroniser de la realite. Une Desserte peut etre inactive pour plusieurs
 * raisons a la fois, d'ou le ManyToMany plutot qu'un simple raisonId.
 *
 * Rattachee a Desserte (Station x Ligne) plutot qu'a Station directement : une Station peut rester
 * active (vrai lieu, vrai service aujourd'hui - ex: un arret de bus) tout en ayant UNE Desserte
 * precise definitivement morte (ex: un quai de metro jamais mis en service ou ferme sans
 * reouverture) - remarque utilisateur, cf. "stations fantomes" dans TODO.md. Station::estActive()
 * est calculee a partir de ses Desserte (au moins une active) plutot que d'avoir sa propre Raison :
 * une Station sans aucune Desserte du tout (aucun service jamais imagine pour elle) n'a jamais
 * vraiment de sens en pratique (remarque utilisateur : on ne cree pas un arret sans prevoir qu'il
 * sera un jour relie a quelque chose) - dans ce cas une Desserte "generique" (Ligne nulle) porte
 * la Raison, migration Version20260829110000.
 */
#[ORM\Entity(repositoryClass: RaisonRepository::class)]
class Raison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $label = null;

    /**
     * @var Collection<int, Desserte>
     */
    #[ORM\ManyToMany(targetEntity: Desserte::class, inversedBy: 'raisons')]
    private Collection $dessertes;

    public function __construct()
    {
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
        }

        return $this;
    }

    public function removeDesserte(Desserte $desserte): static
    {
        $this->dessertes->removeElement($desserte);

        return $this;
    }
}
