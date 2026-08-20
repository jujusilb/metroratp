<?php

namespace App\Entity;

use App\Repository\ArretTransporteurRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Referentiel officiel IDFM au niveau ArT (Arret Transporteur - un arret physique d'un operateur
 * donne : un poteau de bus, un quai precis, pas toute la Station). Complementaire a
 * EquipementArret (qui releve des tags OpenStreetMap pour le meme niveau ArT) : ici, l'
 * accessibilite/signalisation vient directement du referentiel officiel IDFM, pas d'OSM -
 * information differente, pas redondante (99.99% des ArT d'EquipementArret existent aussi ici,
 * mais les deux entites restent volontairement separees plutot que fusionnees : rattachees
 * chacune independamment a Station, pas l'une a l'autre - une vraie hierarchie ArT->Station
 * unifiee serait un refactor plus lourd, non entrepris ici, voir TODO.md).
 *
 * Rattache a Station via relations.csv (ArTId -> ZdCId -> Station.codeExterne), meme mecanisme
 * officiel que PoleEchange/EquipementArret. Cle stable artId, reimportable sans doublons.
 */
#[ORM\Entity(repositoryClass: ArretTransporteurRepository::class)]
class ArretTransporteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private ?int $artId = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    /**
     * bus / rail / metro / tram / cableway (ArTType du referentiel IDFM).
     */
    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    private ?int $zoneTarifaire = null;

    #[ORM\Column(nullable: true)]
    private ?bool $estAccessible = null;

    #[ORM\Column(nullable: true)]
    private ?bool $signalisationSonore = null;

    #[ORM\Column(nullable: true)]
    private ?bool $signalisationVisuelle = null;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    #[ORM\ManyToOne]
    private ?Station $station = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArtId(): ?int
    {
        return $this->artId;
    }

    public function setArtId(int $artId): static
    {
        $this->artId = $artId;

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

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

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

    public function getZoneTarifaire(): ?int
    {
        return $this->zoneTarifaire;
    }

    public function setZoneTarifaire(?int $zoneTarifaire): static
    {
        $this->zoneTarifaire = $zoneTarifaire;

        return $this;
    }

    public function isEstAccessible(): ?bool
    {
        return $this->estAccessible;
    }

    public function setEstAccessible(?bool $estAccessible): static
    {
        $this->estAccessible = $estAccessible;

        return $this;
    }

    public function isSignalisationSonore(): ?bool
    {
        return $this->signalisationSonore;
    }

    public function setSignalisationSonore(?bool $signalisationSonore): static
    {
        $this->signalisationSonore = $signalisationSonore;

        return $this;
    }

    public function isSignalisationVisuelle(): ?bool
    {
        return $this->signalisationVisuelle;
    }

    public function setSignalisationVisuelle(?bool $signalisationVisuelle): static
    {
        $this->signalisationVisuelle = $signalisationVisuelle;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getStation(): ?Station
    {
        return $this->station;
    }

    public function setStation(?Station $station): static
    {
        $this->station = $station;

        return $this;
    }
}
