<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Station;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use App\Entity\TypeTroncon;
use App\Repository\DesserteRepository;
use App\Repository\LigneRepository;
use App\Repository\StationRepository;
use App\Repository\TypeDesserteRepository;
use App\Repository\TypeTronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Meme principe que ConstruireTopologieBusCommand, pour les lignes numerotees 20-100 exploitees
 * par d'autres reseaux que la RATP (Keolis Roissy, Keolis Argenteuil, Transdev Boucle des Lys /
 * Vallee du Loing / Nord Seine-Saint-Denis / Coteaux de la Marne, Keolis Nord Val d'Oise), qui
 * reutilisent independamment des numeros deja pris par une ligne RATP (ex: leur "34" n'a rien a
 * voir avec le bus RATP 34 — deux Ligne distinctes, chacune avec son propre gestionnaire/
 * codeExterne/Desserte).
 *
 * Commande separee de ConstruireTopologieBusCommand car le CSV source est cle par codeExterne
 * (pas par numero affiche) : plusieurs de ces lignes partagent le meme numero (34, 100) sans etre
 * la meme ligne physique, ce qui rendrait la cle "route_label" du CSV RATP ambigue ici.
 *
 * A usage unique par ligne : ignore une ligne qui a deja des troncons.
 *
 * Lit plusieurs fichiers CSV (memes colonnes) : le premier pour les lignes 20-100 hors RATP,
 * le second pour le reste de la plage 101-299 hors RATP (ATM Croix du Sud, Keolis Grand Paris
 * Vallee de la Marne, ligne 282 — voir extraire_troncons_bus_101_299_restant.php et TODO.md), le
 * troisieme pour TOUTES les lignes de bus/car restantes toutes plages confondues (~1167 lignes,
 * decouvert le 2026-08-20 en croisant Desserte/Troncon - voir extraire_troncons_bus_reste.php :
 * meme algorithme, mais liste de lignes a traiter lue programmatiquement en base plutot que tapee
 * a la main, ce qui leve la limite de volume qui bloquait jusqu'ici une reprise complete).
 * Le quatrieme fichier n'est pas du bus : les 2 dernieres lignes du reseau sans aucune topologie
 * (Cable A / C1 a Creteil, Funiculaire de Montmartre) - la commande etant deja 100% generique
 * (cle par codeExterne, sans hypothese sur le mode), reutilisee telle quelle plutot que dupliquee
 * pour 5 troncons (voir extraire_troncons_telepherique_funiculaire.php).
 * Fichiers separes plutot que fusionner dans le premier, pour ne pas re-generer des donnees deja
 * verifiees (meme convention que pour troncons_rer.csv/troncons_rer_c.csv).
 */
#[AsCommand(name: 'app:construire-topologie-bus-autres-operateurs', description: 'Construit les troncons des lignes de bus hors RATP (20-100, 101-299 restant, et le reste), plus le telepherique et le funiculaire, depuis les CSV extraits')]
class ConstruireTopologieBusAutresOperateursCommand extends Command
{
    /** @var string[] */
    private const TRONCONS_CSV = [
        'documentation/scripts/donnees-extraites/troncons_bus_autres_operateurs.csv',
        'documentation/scripts/donnees-extraites/troncons_bus_101_299_restant.csv',
        'documentation/scripts/donnees-extraites/troncons_bus_reste.csv',
        'documentation/scripts/donnees-extraites/troncons_telepherique_funiculaire.csv',
    ];

    private TypeDesserte $depart;
    private TypeDesserte $arrivee;
    private TypeTroncon $exterieur;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LigneRepository $ligneRepository,
        private readonly StationRepository $stationRepository,
        private readonly DesserteRepository $desserteRepository,
        private readonly TypeDesserteRepository $typeDesserteRepository,
        private readonly TypeTronconRepository $typeTronconRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->depart = $this->typeDesserteRepository->findOneBy(['label' => 'Départ']);
        $this->arrivee = $this->typeDesserteRepository->findOneBy(['label' => 'Arrivée']);
        $this->exterieur = $this->typeTronconRepository->findOneBy(['label' => 'Exterieur']);

        /** @var array<string, Station> $stationsParCode */
        $stationsParCode = [];
        foreach ($this->stationRepository->findAll() as $station) {
            $code = $station->getCodeExterne();
            if (null !== $code) {
                $stationsParCode[$code] = $station;
            }
        }

        $lignesDejaVues = [];
        $nbTronconsTotal = 0;
        $nbIgnores = 0;

        foreach (self::TRONCONS_CSV as $chemin) {
            $fichier = fopen($chemin, 'r');
            fgetcsv($fichier); // en-tete
            while (false !== ($ligneCsv = fgetcsv($fichier))) {
                [$codeExterne, $zdcA, $zdcB, $nomA, $nomB, $dureeMediane] = $ligneCsv;

                if (!isset($lignesDejaVues[$codeExterne])) {
                    $lignesDejaVues[$codeExterne] = $this->preparerLigne($io, $codeExterne);
                }
                $ligne = $lignesDejaVues[$codeExterne];
                if (null === $ligne) {
                    continue;
                }

                $desserteA = $this->trouverDesserte($ligne, $stationsParCode, $zdcA, $nomA);
                $desserteB = $this->trouverDesserte($ligne, $stationsParCode, $zdcB, $nomB);
                if (null === $desserteA || null === $desserteB) {
                    $io->warning(sprintf(
                        'Ligne %s : desserte introuvable pour "%s" ou "%s" (station sans codeExterne correspondant ?), tronçon ignoré.',
                        $codeExterne,
                        $nomA,
                        $nomB,
                    ));
                    ++$nbIgnores;
                    continue;
                }

                $duree = '' !== $dureeMediane ? (int) $dureeMediane : null;
                $this->creerTronconBidirectionnel($desserteA, $desserteB, $duree);
                ++$nbTronconsTotal;
            }
            fclose($fichier);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d troncons crees sur %d ligne(s) de bus hors RATP (%d ignores, desserte introuvable).',
            $nbTronconsTotal,
            \count(array_filter($lignesDejaVues)),
            $nbIgnores,
        ));

        return Command::SUCCESS;
    }

    /**
     * Retrouve la Ligne pour ce codeExterne et verifie qu'elle n'a pas deja de troncons (usage
     * unique). Retourne null si la ligne doit etre ignoree (introuvable, ou deja construite).
     */
    private function preparerLigne(SymfonyStyle $io, string $codeExterne): ?Ligne
    {
        $ligne = $this->ligneRepository->findOneBy(['codeExterne' => $codeExterne]);
        if (null === $ligne) {
            $io->warning("Ligne (codeExterne $codeExterne) introuvable en base, ignoree.");

            return null;
        }

        $dejaConstruite = \count($ligne->getDessertes()->filter(
            fn (Desserte $d) => $d->getTronconDessertes()->count() > 0
        )) > 0;
        if ($dejaConstruite) {
            $io->warning("Ligne {$ligne->getLabel()} (codeExterne $codeExterne) deja construite, ignoree.");

            return null;
        }

        return $ligne;
    }

    /**
     * @param array<string, Station> $stationsParCode
     */
    private function trouverDesserte(Ligne $ligne, array $stationsParCode, string $zdc, string $nomPourErreur): ?Desserte
    {
        $station = $stationsParCode[$zdc] ?? null;
        if (null === $station) {
            return null;
        }

        return $this->desserteRepository->findOneBy(['ligne' => $ligne, 'station' => $station]);
    }

    private function creerTronconBidirectionnel(Desserte $a, Desserte $b, ?int $dureeSecondes): Troncon
    {
        $troncon = new Troncon();
        $troncon->setTypeTroncon($this->exterieur);
        $troncon->setDureeReelleSecondes($dureeSecondes);
        $this->entityManager->persist($troncon);

        $this->creerTronconDesserte($troncon, $a, $this->depart);
        $this->creerTronconDesserte($troncon, $b, $this->arrivee);
        $this->creerTronconDesserte($troncon, $b, $this->depart);
        $this->creerTronconDesserte($troncon, $a, $this->arrivee);

        return $troncon;
    }

    private function creerTronconDesserte(Troncon $troncon, Desserte $desserte, TypeDesserte $role): void
    {
        $tronconDesserte = new TronconDesserte();
        $tronconDesserte->setTroncon($troncon);
        $tronconDesserte->setDesserte($desserte);
        $tronconDesserte->setTypeDesserte($role);
        $this->entityManager->persist($tronconDesserte);
    }
}
