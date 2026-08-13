<?php

namespace App\Command;

use App\Entity\Acces;
use App\Entity\Ligne;
use App\Entity\PositionRame;
use App\Entity\Station;
use App\Repository\StationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplit PositionRame depuis conseils_position.csv (extrait de positionnement-dans-la-rame.csv +
 * stops.txt GTFS IDFM - voir documentation/scripts/extraire_conseils_position.php ; le feed GTFS
 * complet, ~1,3 Go, n'est jamais commit). Chaque ligne donne, pour une Station et une Ligne, ou se
 * placer dans la rame pour arriver au plus pres d'une sortie (Acces, quand identifiable via son
 * codeExterne) ou d'un point de correspondance (destination textuelle sinon).
 *
 * Rattachement de la Station par StationRepository::trouverIdCanoniqueParZdc() (comme
 * app:construire-acces-sorties), pas directement par codeExterne : sinon les conseils
 * atterriraient sur la Station ZdC-liee plutot que la Station "originale" que /station/{id}
 * affiche reellement (voir TODO.md, doublons de Station).
 *
 * Reconstruction complete a chaque execution (purge avant import), meme logique que
 * app:construire-acces-sorties : donnee de reference importee, pas saisie manuellement.
 *
 * A executer APRES app:construire-acces-sorties (qui purge/recree les Acces : executer cette
 * commande avant casserait le rattachement acces_id ici).
 */
#[AsCommand(name: 'app:construire-positions-rame', description: 'Reconstruit PositionRame depuis conseils_position.csv')]
class ConstruirePositionsRameCommand extends Command
{
    private const CONSEILS_CSV = 'documentation/scripts/donnees-extraites/conseils_position.csv';
    private const TAILLE_LOT = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StationRepository $stationRepository,
    ) {
        parent::__construct();
    }

    /**
     * @return \Generator<int, array<string, string>>
     */
    private function lireCsv(string $chemin): \Generator
    {
        $fichier = fopen($chemin, 'r');
        $header = fgetcsv($fichier);
        while (false !== ($ligne = fgetcsv($fichier))) {
            yield array_combine($header, $ligne);
        }
        fclose($fichier);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Chargement des Stations (par codeExterne), Lignes (par label) et Acces (par codeExterne)...');
        $stationIdParZdc = $this->stationRepository->trouverIdCanoniqueParZdc();
        // Par label, pas par codeExterne : le codeExterne stocke sur les Ligne de metro est
        // incoherent avec le GTFS actuel (voir docblock de la commande). En cas de doublon de
        // label (metro : Ligne "originale" + doublons crees par l'import complet, voir TODO.md),
        // on prefere la ligne sans codeExterne (l'originale, celle reellement utilisee par les
        // Desserte/Troncon existants).
        $ligneIdParLabel = [];
        foreach ($connexion->executeQuery('SELECT UPPER(label) AS label, id, code_externe FROM ligne ORDER BY (code_externe IS NULL) DESC')->iterateAssociative() as $row) {
            if (!isset($ligneIdParLabel[$row['label']])) {
                $ligneIdParLabel[$row['label']] = (int) $row['id'];
            }
        }
        $accesIdParCode = [];
        foreach ($connexion->executeQuery('SELECT code_externe, id FROM acces WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $accesIdParCode[$row['code_externe']] = (int) $row['id'];
        }
        $io->info(sprintf('%d Stations avec codeExterne, %d labels de Ligne, %d Acces avec codeExterne.', \count($stationIdParZdc), \count($ligneIdParLabel), \count($accesIdParCode)));

        $io->section('Purge des PositionRame existantes...');
        $connexion->executeStatement('DELETE FROM position_rame');

        $io->section('Lecture de conseils_position.csv et creation des PositionRame...');
        $nbCrees = 0;
        $nbEnAttente = 0;
        $nbIgnores = 0;

        foreach ($this->lireCsv(self::CONSEILS_CSV) as $ligne) {
            $stationId = $stationIdParZdc[$ligne['zdc']] ?? null;
            $ligneId = $ligneIdParLabel[mb_strtoupper($ligne['ligneLabel'])] ?? null;
            if (null === $stationId || null === $ligneId) {
                ++$nbIgnores;
                continue;
            }

            $positionRame = new PositionRame();
            $positionRame->setStation($this->entityManager->getReference(Station::class, $stationId));
            $positionRame->setLigne($this->entityManager->getReference(Ligne::class, $ligneId));
            $positionRame->setDestination(mb_substr($ligne['destination'], 0, 150));
            $positionRame->setLabelPosition($ligne['labelPosition']);
            $positionRame->setPosition((int) $ligne['position']);
            $positionRame->setPositionMax((int) $ligne['positionMax']);
            $positionRame->setEquipement('' !== $ligne['equipement'] ? $ligne['equipement'] : null);

            if ('' !== $ligne['accId']) {
                $accesId = $accesIdParCode[$ligne['accId']] ?? null;
                if (null !== $accesId) {
                    $positionRame->setAcces($this->entityManager->getReference(Acces::class, $accesId));
                }
            }

            $this->entityManager->persist($positionRame);
            ++$nbCrees;

            if (++$nbEnAttente >= self::TAILLE_LOT) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $nbEnAttente = 0;
                $io->write('.');
            }
        }
        $this->entityManager->flush();
        $io->newLine();

        $io->success(sprintf('%d PositionRame creees (%d ignorees : Station ou Ligne introuvable).', $nbCrees, $nbIgnores));

        return Command::SUCCESS;
    }
}
