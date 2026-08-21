<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use App\Entity\TypeTroncon;
use App\Repository\LigneRepository;
use App\Repository\TypeDesserteRepository;
use App\Repository\TypeTronconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Construit la topologie des 8 lignes Transilien (H, J, K, L, N, P, R, U) encore sans aucun
 * troncon (252 Desserte isolees, decouvert le 2026-08-21 apres la completion du reseau
 * bus/RER/telepherique/funiculaire - voir documentation/TODO.md).
 *
 * Meme principe que ConstruireMaillageRerDCommand : rattachement par label de Station au sein de
 * chaque Ligne (les codeExterne GTFS actuels des Ligne de metro/bus se sont deja reveles peu
 * fiables lors de sessions precedentes - voir ConstruireTopologieRerCommand, meme technique).
 * Volontairement PAS de Direction/Mission : plusieurs de ces lignes (H notamment, avec sa boucle
 * Argenteuil/Ermont) ont un excedent d'aretes par rapport a un arbre pur (verifie : aretes - (
 * noeuds - composantes) > 0 sur 7 des 8 lignes), signe d'un vrai maillage local comme celui deja
 * traite sur le RER D - plutot que d'investiguer chaque ligne une par une pour distinguer un
 * embranchement legitime d'un maillage reel, seuls les Troncon/TronconDesserte sont construits ici
 * (suffisant pour TrajetFinder::construireGraphe(), qui ne lit pas Direction/Mission).
 *
 * Idempotente : ne cree que les paires de Desserte pas deja reliees par un Troncon direct.
 */
#[AsCommand(name: 'app:construire-topologie-transilien', description: 'Construit les troncons des 8 lignes Transilien (H, J, K, L, N, P, R, U) depuis le CSV extrait, sans Direction/Mission')]
class ConstruireTopologieTransilienCommand extends Command
{
    private const TRONCONS_CSV = 'documentation/scripts/donnees-extraites/troncons_transilien.csv';

    /**
     * Paires manuelles (nom GTFS => label DB) : memes lieux reels, simple absence de tiret cote
     * DB. Meme principe que ConstruireTopologieRerCommand::ASSOCIATIONS_MANUELLES.
     */
    private const ASSOCIATIONS_MANUELLES = [
        'Neuville - Université' => 'Neuville Université',
        'Saint-Nom-la-Bretèche - Forêt de Marly' => 'Saint-Nom-la-Bretèche Forêt de Marly',
        'Viroflay - Rive Droite' => 'Viroflay Rive Droite',
        'Nemours - Saint-Pierre' => 'Nemours Saint-Pierre',
    ];

    /** @var array<string, string> label affiche => codeExterne */
    private const LIGNES_CODES = [
        'H' => 'C01737',
        'J' => 'C01739',
        'K' => 'C01738',
        'L' => 'C01740',
        'N' => 'C01736',
        'P' => 'C01730',
        'R' => 'C01731',
        'U' => 'C01741',
    ];

    private TypeDesserte $depart;
    private TypeDesserte $arrivee;
    private TypeTroncon $exterieur;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LigneRepository $ligneRepository,
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

        /** @var array<string, array<string, Desserte>> label ligne => label station => Desserte */
        $dessertesParLigne = [];
        /** @var array<string, Ligne> */
        $lignes = [];
        foreach (self::LIGNES_CODES as $labelLigne => $codeExterne) {
            $ligne = $this->ligneRepository->findOneBy(['codeExterne' => $codeExterne]);
            if (null === $ligne) {
                $io->warning("Ligne $labelLigne (codeExterne $codeExterne) introuvable, ignoree.");
                continue;
            }
            $lignes[$labelLigne] = $ligne;

            $parStation = [];
            foreach ($ligne->getDessertes() as $desserte) {
                $label = $desserte->getStation()?->getLabel();
                if (null !== $label) {
                    $parStation[$label] = $desserte;
                }
            }
            $dessertesParLigne[$labelLigne] = $parStation;
        }

        $fichier = fopen(self::TRONCONS_CSV, 'r');
        fgetcsv($fichier); // en-tete
        $nbCreesParLigne = [];
        $nbIgnores = 0;
        while (false !== ($ligneCsv = fgetcsv($fichier))) {
            [$labelLigne, , , $nomA, $nomB, $dureeMediane] = $ligneCsv;

            if (!isset($lignes[$labelLigne])) {
                continue;
            }

            $nomA = self::ASSOCIATIONS_MANUELLES[$nomA] ?? $nomA;
            $nomB = self::ASSOCIATIONS_MANUELLES[$nomB] ?? $nomB;
            $desserteA = $dessertesParLigne[$labelLigne][$nomA] ?? null;
            $desserteB = $dessertesParLigne[$labelLigne][$nomB] ?? null;
            if (null === $desserteA || null === $desserteB) {
                $io->warning(sprintf('Ligne %s : station "%s" ou "%s" introuvable parmi ses dessertes, arete ignoree.', $labelLigne, $nomA, $nomB));
                ++$nbIgnores;
                continue;
            }

            if ($this->tronconExisteDeja($desserteA, $desserteB)) {
                continue;
            }

            $duree = '' !== $dureeMediane ? (int) $dureeMediane : null;
            $this->creerTronconBidirectionnel($desserteA, $desserteB, $duree);
            $nbCreesParLigne[$labelLigne] = ($nbCreesParLigne[$labelLigne] ?? 0) + 1;
        }
        fclose($fichier);

        $this->entityManager->flush();

        foreach ($nbCreesParLigne as $labelLigne => $nb) {
            $io->writeln("Ligne $labelLigne : $nb troncons crees.");
        }
        $io->success(sprintf(
            '%d troncons crees au total sur %d ligne(s), %d ignores (station introuvable).',
            array_sum($nbCreesParLigne),
            \count($nbCreesParLigne),
            $nbIgnores,
        ));

        return Command::SUCCESS;
    }

    private function tronconExisteDeja(Desserte $a, Desserte $b): bool
    {
        $connexion = $this->entityManager->getConnection();
        $resultat = $connexion->executeQuery(
            <<<'SQL'
                SELECT 1
                FROM troncon_desserte tda
                JOIN troncon_desserte tdb ON tdb.troncon_id = tda.troncon_id AND tdb.desserte_id != tda.desserte_id
                WHERE tda.desserte_id = :a AND tdb.desserte_id = :b
                LIMIT 1
                SQL,
            ['a' => $a->getId(), 'b' => $b->getId()]
        )->fetchOne();

        return false !== $resultat;
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
