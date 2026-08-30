<?php

namespace App\Entity;

use App\Repository\LigneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneRepository::class)]
class Ligne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $label = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $couleur = null;

    /**
     * Identifiant IDFM (referentiel-des-lignes / GTFS route_id, ex: "C01742" pour le RER A).
     * Indispensable au-dela du metro/RER : de nombreuses lignes de bus d'operateurs differents
     * partagent le meme numero affiche (label), seul cet identifiant est vraiment unique.
     */
    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $codeExterne = null;

    /**
     * Trace geometrique reel de la Ligne (WGS84), depuis le dataset IDFM
     * "traces-des-lignes-de-transport-en-commun-idfm" - voir
     * documentation/scripts/extraire_traces_lignes.php. JSON : liste de lignes (une Ligne peut
     * avoir plusieurs branches/variantes disjointes), chacune une liste de points [lon, lat].
     * Sert a dessiner le trace reel (suit les rues/rails) plutot qu'une ligne droite entre les
     * stations sur la carte du calculateur de trajet. Null si aucun trace trouve pour cette Ligne.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $trace = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    private ?TypeTransport $typeTransport = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    private ?Gestionnaire $gestionnaire = null;

    /**
     * @var Collection<int, Desserte>
     */
    #[ORM\OneToMany(targetEntity: Desserte::class, mappedBy: 'ligne')]
    private Collection $dessertes;

    /**
     * @var Collection<int, MaterielLigne>
     */
    #[ORM\OneToMany(targetEntity: MaterielLigne::class, mappedBy: 'ligne')]
    private Collection $materielLignes;

    /**
     * @var Collection<int, DocumentLigne>
     */
    #[ORM\OneToMany(targetEntity: DocumentLigne::class, mappedBy: 'ligne')]
    private Collection $documents;

    /**
     * Depots (centres bus) desservant cette Ligne, avec periode - voir Depot/DepotLigne, le
     * materiel de bus se deduit par jointure Ligne -> Depot -> MaterielDepot plutot que d'etre
     * rattache directement a la Ligne.
     *
     * @var Collection<int, DepotLigne>
     */
    #[ORM\OneToMany(targetEntity: DepotLigne::class, mappedBy: 'ligne')]
    private Collection $depotLignes;

    /**
     * Plages horaires de service (voir HoraireLigne) - editees directement ici, meme principe
     * que materielLignes (pas de page CRUD dediee).
     *
     * @var Collection<int, HoraireLigne>
     */
    #[ORM\OneToMany(targetEntity: HoraireLigne::class, mappedBy: 'ligne', cascade: ['persist'], orphanRemoval: true)]
    private Collection $horaireLignes;

    /**
     * @var Collection<int, Direction>
     */
    #[ORM\OneToMany(targetEntity: Direction::class, mappedBy: 'ligne')]
    private Collection $directions;

    /**
     * Date a laquelle la Ligne est devenue entierement automatique (conduite sans conducteur,
     * "GoA4") sur la totalite de son parcours - une propriete de la ligne entiere (materiel
     * roulant + signalisation), contrairement aux portes palieres qui s'installent quai par quai
     * (voir Desserte::datePortePaliere). Concerne tres peu de Ligne (1, 4, 14 a ce jour) : null
     * partout ailleurs, y compris pour une ligne dont l'automatisation est seulement en projet
     * (ex. Ligne 13, votee en 2022 mais pas encore realisee).
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateAutomatisationTotale = null;

    public function __construct()
    {
        $this->dessertes = new ArrayCollection();
        $this->materielLignes = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->depotLignes = new ArrayCollection();
        $this->horaireLignes = new ArrayCollection();
        $this->directions = new ArrayCollection();
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

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;

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

    public function getTrace(): ?array
    {
        return $this->trace;
    }

    public function setTrace(?array $trace): static
    {
        $this->trace = $trace;

        return $this;
    }

    public function getTypeTransport(): ?TypeTransport
    {
        return $this->typeTransport;
    }

    public function setTypeTransport(?TypeTransport $typeTransport): static
    {
        $this->typeTransport = $typeTransport;

        return $this;
    }

    /**
     * Cle de mode utilisee pour le filtre "Metro / Tram / RER / Bus RATP / Bus tiers /
     * Telepherique / Funiculaire / Train" du calculateur de trajet. Null pour les types sans case
     * a cocher dediee (Car, TER, navettes aeroport...) : ces lignes ne sont alors jamais
     * proposees quand un filtre est actif, mais restent utilisables quand aucun filtre n'est
     * applique.
     */
    public function getModeFiltre(): ?string
    {
        return match ($this->typeTransport?->getLabel()) {
            'Métro' => 'metro',
            'Tramway' => 'tram',
            'RER' => 'rer',
            'Bus' => 'RATP' === $this->gestionnaire?->getLabel() ? 'bus_ratp' : 'bus_tiers',
            'Téléphérique' => 'telepherique',
            'Funiculaire' => 'funiculaire',
            'Train' => 'train',
            default => null,
        };
    }

    public function getGestionnaire(): ?Gestionnaire
    {
        return $this->gestionnaire;
    }

    public function setGestionnaire(?Gestionnaire $gestionnaire): static
    {
        $this->gestionnaire = $gestionnaire;

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
            $desserte->setLigne($this);
        }

        return $this;
    }

    public function removeDesserte(Desserte $desserte): static
    {
        if ($this->dessertes->removeElement($desserte)) {
            // set the owning side to null (unless already changed)
            if ($desserte->getLigne() === $this) {
                $desserte->setLigne(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MaterielLigne>
     */
    public function getMaterielLignes(): Collection
    {
        return $this->materielLignes;
    }

    public function addMaterielLigne(MaterielLigne $materielLigne): static
    {
        if (!$this->materielLignes->contains($materielLigne)) {
            $this->materielLignes->add($materielLigne);
            $materielLigne->setLigne($this);
        }

        return $this;
    }

    public function removeMaterielLigne(MaterielLigne $materielLigne): static
    {
        if ($this->materielLignes->removeElement($materielLigne)) {
            // set the owning side to null (unless already changed)
            if ($materielLigne->getLigne() === $this) {
                $materielLigne->setLigne(null);
            }
        }

        return $this;
    }

    public function getDateAutomatisationTotale(): ?\DateTime
    {
        return $this->dateAutomatisationTotale;
    }

    public function setDateAutomatisationTotale(?\DateTime $dateAutomatisationTotale): static
    {
        $this->dateAutomatisationTotale = $dateAutomatisationTotale;

        return $this;
    }

    /**
     * @return Collection<int, DocumentLigne>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(DocumentLigne $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setLigne($this);
        }

        return $this;
    }

    public function removeDocument(DocumentLigne $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getLigne() === $this) {
                $document->setLigne(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DepotLigne>
     */
    public function getDepotLignes(): Collection
    {
        return $this->depotLignes;
    }

    /**
     * @return Collection<int, HoraireLigne>
     */
    public function getHoraireLignes(): Collection
    {
        return $this->horaireLignes;
    }

    public function addHoraireLigne(HoraireLigne $horaireLigne): static
    {
        if (!$this->horaireLignes->contains($horaireLigne)) {
            $this->horaireLignes->add($horaireLigne);
            $horaireLigne->setLigne($this);
        }

        return $this;
    }

    public function removeHoraireLigne(HoraireLigne $horaireLigne): static
    {
        if ($this->horaireLignes->removeElement($horaireLigne)) {
            if ($horaireLigne->getLigne() === $this) {
                $horaireLigne->setLigne(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Direction>
     */
    public function getDirections(): Collection
    {
        return $this->directions;
    }

    public function getNombreStations(): int
    {
        return count($this->dessertes);
    }

    /**
     * Labels des stations terminus de la ligne, deduits du graphe des troncons
     * (une desserte sans troncon entrant ou sans troncon sortant est un terminus).
     * Une ligne avec des branches (ex: 7, 13) peut avoir plus de deux terminus.
     *
     * @return string[]
     */
    public function getTerminusLabels(): array
    {
        $hasTroncons = false;

        foreach ($this->dessertes as $desserte) {
            if ($desserte->getNombreTronconsDistincts() > 0) {
                $hasTroncons = true;
                break;
            }
        }

        if (!$hasTroncons) {
            return [];
        }

        $labels = [];
        foreach ($this->dessertes as $desserte) {
            if ($desserte->getNombreTronconsDistincts() <= 1) {
                $label = $desserte->getStation()?->getLabel();
                if (null !== $label) {
                    $labels[] = $label;
                }
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * Parcours ordonne de la ligne, decoupe en segments lineaires deja aplatis pour
     * l'affichage : chaque segment est une liste continue de stations (sans embranchement),
     * suivie le cas echeant d'une liste de segments-enfants la ou la ligne se divise
     * (ex: ligne 7 a Maison Blanche). Quand deux branches fusionnent vers une meme station
     * (ex: ligne 13, Les Courtilles et Saint-Denis - Universite qui rejoignent La Fourche),
     * le segment de la seconde branche s'arrete sur cette station avec rejoint=true plutot
     * que de re-afficher la suite commune une deuxieme fois.
     *
     * @return array<int, array{stations: array<int, array{label: string, rejoint: bool, varianteMaillage: ?string, correspondances: array}>, branches: array}>
     */
    public function getParcoursSegments(): array
    {
        // Un seul terminus suffit comme racine : le graphe est desormais bidirectionnel
        // (troncon_desserte porte les 2 sens), donc parcourir depuis n'importe quel
        // terminus reconstruit tout l'arbre (embranchements compris). Prendre TOUS les
        // terminus comme racines re-parcourrait la ligne entiere depuis chaque bout.
        $root = null;
        foreach ($this->dessertes as $desserte) {
            if (1 === $desserte->getNombreTronconsDistincts()) {
                $root = $desserte;
                break;
            }
        }
        $roots = null !== $root ? [$root] : [];

        $visited = [];
        $buildSegment = function (Desserte $desserte, ?Troncon $arrivingFrom) use (&$buildSegment, &$visited): array {
            $stations = [];
            $current = $desserte;

            while (true) {
                $id = $current->getId();
                if (isset($visited[$id])) {
                    $stations[] = [
                        'label' => $current->getStation()?->getLabel() ?? '?',
                        'stationId' => $current->getStation()?->getId(),
                        'rejoint' => true,
                        'varianteMaillage' => $arrivingFrom?->getVarianteMaillage(),
                        'correspondances' => $this->getCorrespondances($current),
                    ];

                    return ['stations' => $stations, 'branches' => []];
                }
                $visited[$id] = true;
                $stations[] = [
                    'label' => $current->getStation()?->getLabel() ?? '?',
                    'stationId' => $current->getStation()?->getId(),
                    'rejoint' => false,
                    'correspondances' => $this->getCorrespondances($current),
                ];

                $nextTroncons = $current->getTronconsDepart($arrivingFrom);

                if (1 === count($nextTroncons)) {
                    $troncon = $nextTroncons[0];
                    $next = $troncon->getDesserteForRole('Arrivée', $current);
                    if (null === $next) {
                        return ['stations' => $stations, 'branches' => []];
                    }
                    $current = $next;
                    $arrivingFrom = $troncon;
                    continue;
                }

                $branches = [];
                foreach ($nextTroncons as $troncon) {
                    $next = $troncon->getDesserteForRole('Arrivée', $current);
                    if (null === $next) {
                        continue;
                    }
                    $branche = $buildSegment($next, $troncon);
                    // Une branche qui rejoint la ligne des son tout premier arret n'apporte
                    // aucune information : c'est un pur artefact de l'exploration d'un maillage
                    // (cycle reel dans le graphe, ex. RER D) par un chemin different de celui
                    // qui a deja parcouru et affiche ce meme point de jonction. L'ignorer plutot
                    // que d'afficher un encart vide et confus.
                    if (1 === count($branche['stations']) && true === $branche['stations'][0]['rejoint']) {
                        continue;
                    }
                    $branches[] = $branche;
                }

                return ['stations' => $stations, 'branches' => $branches];
            }
        };

        $segments = [];
        foreach ($roots as $root) {
            $segments[] = $buildSegment($root, null);
        }

        return $segments;
    }

    /**
     * Les autres lignes qui desservent la meme station que cette desserte (correspondances),
     * triees par label. Limite aux lignes "structurantes" (Metro/RER/Tramway/Train/Telepherique/
     * Funiculaire) : le Bus est exclu, sous peine de noyer l'affichage d'un simple parcours de
     * ligne sous des dizaines de lignes de bus/Noctilien a chaque grand pole (ex. La Defense, 26
     * correspondances bus). Le detail complet, bus compris, reste consultable sur la fiche Station.
     *
     * @return array<int, array{label: string, couleur: ?string}>
     */
    private function getCorrespondances(Desserte $desserte): array
    {
        $station = $desserte->getStation();
        if (null === $station) {
            return [];
        }

        $correspondances = [];
        foreach ($station->getDessertes() as $autreDesserte) {
            $autreLigne = $autreDesserte->getLigne();
            if (null !== $autreLigne && $autreLigne !== $this && 'Bus' !== $autreLigne->getTypeTransport()?->getLabel()) {
                $correspondances[$autreLigne->getId()] = [
                    'label' => $autreLigne->getLabel(),
                    'couleur' => $autreLigne->getCouleur(),
                    'ligneId' => $autreLigne->getId(),
                ];
            }
        }

        usort($correspondances, static fn (array $a, array $b): int => $a['label'] <=> $b['label']);

        return array_values($correspondances);
    }
}
