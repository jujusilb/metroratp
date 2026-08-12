<?php

namespace App\Command;

use App\Entity\Acces;
use App\Entity\Sortie;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplit Acces (une sortie physique : nom de rue, numero) et Sortie (le lien vers la Station
 * desservie) depuis acces_entrees.csv (extrait d'acces.csv + stops.txt GTFS IDFM - voir
 * documentation/scripts/extraire_acces_entrees.php ; le feed GTFS complet, ~1,3 Go, n'est jamais
 * commit). Ce fichier derive fusionne deja : le libelle/numero officiels du dataset "acces"
 * (data.iledefrance-mobilites.fr) quand disponibles, sinon stop_name/stop_code du GTFS (~7 acces
 * absents de l'export CSV) ; et le rattachement a la ZdC (parent_station de stops.txt - verifie :
 * ce n'est pas la Zone d'Arrets intermediaire, mais bien la ZdC = Station::codeExterne).
 *
 * Pas de champ PMR dans ce referentiel (verifie sur le schema du dataset "acces" : aucune colonne
 * accessibilite). Le dataset "accessibilite-en-gare" existe mais a un tout autre grain (459 gares,
 * pas 2500+ acces individuels) : Acces::isAccessible reste NULL, ce qui reflete l'absence reelle
 * de donnee plutot qu'une fausse valeur.
 *
 * Reconstruction complete a chaque execution (purge Acces/Sortie avant import) : les 1068 lignes
 * precedentes etaient une saisie manuelle partielle, concentree sur des Station "originales" sans
 * codeExterne (le probleme de doublons de Station documente dans TODO.md), donc pas reconciliable
 * proprement avec un import cle par ZdC.
 */
#[AsCommand(name: 'app:construire-acces-sorties', description: 'Reconstruit Acces/Sortie depuis acces_entrees.csv pour le rattachement aux Stations')]
class ConstruireAccesSortiesCommand extends Command
{
    private const ACCES_ENTREES_CSV = 'documentation/scripts/donnees-extraites/acces_entrees.csv';
    private const TAILLE_LOT = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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

        $io->section('Chargement des Stations connues (par codeExterne / ZdC)...');
        $stationIdParZdc = [];
        foreach ($connexion->executeQuery('SELECT code_externe, id FROM station WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $stationIdParZdc[$row['code_externe']] = (int) $row['id'];
        }
        $io->info(\count($stationIdParZdc).' Stations avec un codeExterne.');

        $io->section('Purge des Acces/Sortie existants...');
        $connexion->executeStatement('DELETE FROM sortie');
        $connexion->executeStatement('DELETE FROM acces');

        $io->section('Lecture de acces_entrees.csv et creation des Acces/Sortie...');
        $nbCrees = 0;
        $nbEnAttente = 0;
        $nbSansStation = 0;
        $nbTotal = 0;

        foreach ($this->lireCsv(self::ACCES_ENTREES_CSV) as $ligne) {
            ++$nbTotal;

            $stationId = $stationIdParZdc[$ligne['zdc']] ?? null;
            if (null === $stationId) {
                ++$nbSansStation;
                continue;
            }

            $acces = new Acces();
            $acces->setLabel('' !== $ligne['label'] ? mb_substr($ligne['label'], 0, 100) : 'Sans nom');
            $acces->setNumero('' !== $ligne['numero'] ? mb_substr($ligne['numero'], 0, 4) : null);
            $this->entityManager->persist($acces);

            $sortie = new Sortie();
            $sortie->setAcces($acces);
            $sortie->setStation($this->entityManager->getReference(Station::class, $stationId));
            $this->entityManager->persist($sortie);

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

        $io->success(sprintf(
            '%d Acces/Sortie crees sur %d acces (%d ignores : ZdC sans Station en base).',
            $nbCrees,
            $nbTotal,
            $nbSansStation,
        ));

        return Command::SUCCESS;
    }
}
