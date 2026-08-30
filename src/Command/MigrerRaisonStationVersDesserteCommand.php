<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Raison;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Migration ponctuelle (voir TODO.md "stations fantomes") : l'inactivite passe de Station a
 * Desserte (une Station peut rester active - ex: un arret de bus - alors qu'une Desserte precise
 * est morte). Pour les 1797 Station deja taguees inactives via l'ancien Station::raisons (aucune
 * Desserte du tout, donc rien de precis a marquer) : cree UNE Desserte "generique" (Ligne nulle,
 * simple support pour porter la Raison - remarque utilisateur : une Station n'existe jamais sans
 * qu'un service ait ete imagine pour elle un jour) par Station concernee, lui rattache la/les
 * meme(s) Raison(s) que la Station portait.
 *
 * Doit etre executee AVANT la migration qui supprime la table raison_station (perte de donnees
 * sinon) - lit directement raison_station en SQL brut, la table/l'association ORM
 * Station::raisons/Raison::stations ayant deja ete retiree du code au moment ou cette commande
 * est ecrite (mais la TABLE, elle, existe encore en prod tant que la migration de suppression n'a
 * pas tourne).
 *
 * Idempotente : ignore une Station qui a deja une Desserte a Ligne nulle.
 */
#[AsCommand(name: 'app:migrer-raison-station-vers-desserte', description: 'Migre les Raison de Station vers une Desserte generique (Ligne nulle), avant suppression de raison_station')]
class MigrerRaisonStationVersDesserteCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $raisonsParStation = [];
        foreach ($connexion->executeQuery('SELECT station_id, raison_id FROM raison_station')->iterateAssociative() as $row) {
            $raisonsParStation[(int) $row['station_id']][] = (int) $row['raison_id'];
        }

        if ([] === $raisonsParStation) {
            $io->success('Aucune ligne dans raison_station : rien a migrer (deja fait, ou table deja vide/supprimee).');

            return Command::SUCCESS;
        }

        $stationRepository = $this->entityManager->getRepository(Station::class);
        $raisonRepository = $this->entityManager->getRepository(Raison::class);
        $desserteRepository = $this->entityManager->getRepository(Desserte::class);

        $nbCreees = 0;
        $nbDejaFaites = 0;
        foreach ($raisonsParStation as $stationId => $raisonIds) {
            $station = $stationRepository->find($stationId);
            if (null === $station) {
                continue;
            }

            $desserteGenerique = $desserteRepository->findOneBy(['station' => $station, 'ligne' => null]);
            if (null !== $desserteGenerique) {
                ++$nbDejaFaites;
                continue;
            }

            $desserteGenerique = new Desserte();
            $desserteGenerique->setStation($station);
            $this->entityManager->persist($desserteGenerique);

            foreach ($raisonIds as $raisonId) {
                $raison = $raisonRepository->find($raisonId);
                if (null !== $raison) {
                    $raison->addDesserte($desserteGenerique);
                }
            }

            ++$nbCreees;
        }

        $this->entityManager->flush();

        $io->success("$nbCreees Desserte generique(s) creee(s) ($nbDejaFaites deja migrees).");

        return Command::SUCCESS;
    }
}
