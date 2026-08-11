<?php

namespace App\Command;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cree les correspondances entre deux Stations DIFFERENTES (contrairement a
 * ConstruireCorrespondancesInterModesCommand, qui regroupe par label de station donc ne relie que
 * des dessertes d'UNE MEME station), a partir de transfers.txt (GTFS IDFM) —
 * documentation/scripts/extraire_correspondances_inter_zdc.php.
 *
 * 99,9% des paires concernees impliquent au moins un arret de bus (voir analyse de session,
 * 2026-08) : c'est la seule source qui permet de relier bus<->bus / bus<->metro / bus<->rer /
 * bus<->tram, un chantier explicitement laisse de cote par ConstruireCorrespondancesInterModesCommand
 * (limite aux modes lourds pour eviter l'explosion combinatoire d'une approche naive "toutes les
 * paires de dessertes a un meme arret" sur ~1400 lignes de bus). Ici, la source GTFS elle-meme fait
 * deja le tri (une paire = une vraie correspondance pietonne officielle documentee), donc pas
 * d'explosion : ~12200 paires de Stations, ~107000 lignes Correspondance en tout (toutes les
 * combinaisons de dessertes entre les deux Stations de chaque paire, comme la commande metro/tram/
 * RER existante).
 *
 * Le "temps de marche" GTFS (min_transfer_time, secondes) est converti en distance (metres) pour
 * rester coherent avec Correspondance::getTempsEstimeMinutes(), qui derive le temps affiche a
 * partir de la distance (vitesse de marche ~0.9 m/s) plutot que de stocker un temps directement.
 *
 * A usage unique par paire de dessertes : ignore les paires qui ont deja une Correspondance (dans
 * un sens ou l'autre), donc rejouable sans creer de doublons.
 */
#[AsCommand(name: 'app:construire-correspondances-bus', description: 'Cree les correspondances entre Stations differentes (bus<->bus/metro/rer/tram) depuis correspondances_inter_zdc.csv')]
class ConstruireCorrespondancesBusCommand extends Command
{
    private const CORRESPONDANCES_CSV = 'documentation/scripts/donnees-extraites/correspondances_inter_zdc.csv';

    // Vitesse de marche moyenne utilisee par Correspondance::getTempsEstimeMinutes() : on
    // convertit le temps GTFS (secondes) en distance equivalente pour rester coherent, plutot
    // que de stocker un temps qui ne correspondrait pas a distance/0.9/60 une fois relu.
    private const VITESSE_MARCHE_M_PAR_S = 0.9;

    private const TAILLE_LOT = 2000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Chargement des Stations par codeExterne (ZdC)...');
        // Requete SQL brute (pas de QueryBuilder/iterate ORM) : Doctrine refuse d'iterer une
        // requete qui joint une association *ToMany (Station -> Desserte), meme en ne
        // selectionnant que des scalaires.
        $connexion = $this->entityManager->getConnection();
        /** @var array<string, int[]> $dessertesParZdc */
        $dessertesParZdc = [];
        foreach ($connexion->executeQuery(
            'SELECT s.code_externe AS code_externe, d.id AS desserte_id
             FROM station s
             INNER JOIN desserte d ON d.station_id = s.id
             WHERE s.code_externe IS NOT NULL'
        )->iterateAssociative() as $row) {
            $dessertesParZdc[$row['code_externe']][] = (int) $row['desserte_id'];
        }
        $io->info(\count($dessertesParZdc).' ZdC avec au moins une desserte en base.');

        $io->section('Chargement des paires de dessertes ayant deja une Correspondance...');
        $pairesExistantes = [];
        foreach ($connexion->executeQuery('SELECT desserte_a_id, desserte_b_id FROM correspondance')->iterateAssociative() as $row) {
            $pairesExistantes[$row['desserte_a_id'].'|'.$row['desserte_b_id']] = true;
        }
        $io->info(\count($pairesExistantes).' correspondances deja existantes.');

        $io->section('Lecture du CSV et creation des correspondances...');
        $fichier = fopen(self::CORRESPONDANCES_CSV, 'r');
        fgetcsv($fichier); // en-tete

        $nbCreees = 0;
        $nbIgnoreesDejaExistantes = 0;
        $nbZdcIntrouvables = 0;
        $nbEnAttente = 0;

        while (false !== ($ligne = fgetcsv($fichier))) {
            [$zdcA, $zdcB, $dureeSecondes] = $ligne;

            $dessertesA = $dessertesParZdc[$zdcA] ?? [];
            $dessertesB = $dessertesParZdc[$zdcB] ?? [];
            if ([] === $dessertesA || [] === $dessertesB) {
                ++$nbZdcIntrouvables;
                continue;
            }

            $distance = (int) round((int) $dureeSecondes * self::VITESSE_MARCHE_M_PAR_S);

            foreach ($dessertesA as $idA) {
                foreach ($dessertesB as $idB) {
                    [$min, $max] = $idA < $idB ? [$idA, $idB] : [$idB, $idA];
                    $cle = "$min|$max";
                    if (isset($pairesExistantes[$cle])) {
                        ++$nbIgnoreesDejaExistantes;
                        continue;
                    }
                    $pairesExistantes[$cle] = true; // evite aussi les doublons intra-CSV

                    $correspondance = new Correspondance();
                    $correspondance->setDesserteA($this->entityManager->getReference(Desserte::class, $min));
                    $correspondance->setDesserteB($this->entityManager->getReference(Desserte::class, $max));
                    $correspondance->setDistance($distance);
                    $this->entityManager->persist($correspondance);
                    ++$nbCreees;
                    ++$nbEnAttente;

                    if ($nbEnAttente >= self::TAILLE_LOT) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                        // clear() detache aussi les references Station/Desserte deja chargees ;
                        // on n'en a plus besoin (on ne travaille plus que sur des ids/tableaux PHP).
                        $nbEnAttente = 0;
                        $io->write('.');
                    }
                }
            }
        }
        fclose($fichier);

        $this->entityManager->flush();
        $io->newLine();

        $io->success(sprintf(
            '%d correspondances creees (%d deja existantes ignorees, %d paires de ZdC sans Station en base).',
            $nbCreees,
            $nbIgnoreesDejaExistantes,
            $nbZdcIntrouvables,
        ));

        return Command::SUCCESS;
    }
}
