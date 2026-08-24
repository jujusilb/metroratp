<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Mission;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
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
 * Corrige un trou isole dans la topologie de la Ligne 3 metro (signale le 2026-08-17, "Wagram
 * totalement absente de la base" - en realite partiellement faux, verifie le 2026-08-24) :
 *
 * La Station "Wagram" (code_externe 71423, Paris 17e) existe bien en base, avec 2 Desserte bus
 * (lignes 31/93), mais aucune Desserte pour la Ligne 3 metro - alors que le GTFS actuel confirme
 * que Wagram est une vraie station de la Ligne 3, entre Malesherbes et Pereire (position 19/24
 * dans l'ordre reel des arrets). Chez nous, un Troncon relie directement Malesherbes<->Pereire
 * (aucun arret entre les deux) : un vrai court-circuit qui saute Wagram.
 *
 * Cause probable : un second Station homonyme existe (id 24172, "Wagram" a Maisons-Laffitte,
 * code_externe different) - le rattachement par simple label lors d'un import passe a
 * vraisemblablement ete evite par prudence (meme discipline que partout ailleurs sur les
 * homonymes), sans jamais etre repris ensuite pour cette Station precise (identifiee ici sans
 * ambiguite par son code_externe, pas par son label).
 *
 * Correction : cree la Desserte manquante, remplace le Troncon direct Malesherbes<->Pereire par 2
 * Troncon (Malesherbes<->Wagram, Wagram<->Pereire), meme convention que le Troncon existant
 * (type_troncon "Interieur", 4 TronconDesserte par Troncon - Depart+Arrivee pour chaque cote,
 * couvrant les 2 sens de circulation).
 *
 * Les 2 Mission existantes sur le troncon direct (une par sens, direction "Gallieni" ou "Pont de
 * Levallois - Bécon") sont repointees vers le TronconDesserte-Depart correspondant du nouveau
 * troncon adjacent, et 2 Mission supplementaires sont creees pour les TronconDesserte-Depart de
 * Wagram (meme numero/service, direction opposee) - sans cette gestion, la suppression du
 * troncon direct est bloquee par la contrainte de cle etrangere Mission->TronconDesserte.
 *
 * Idempotente : ne fait rien si la Desserte Wagram/Ligne 3 existe deja.
 */
#[AsCommand(name: 'app:corriger-troncon-wagram', description: 'Ajoute la Desserte Wagram manquante sur la Ligne 3 metro et corrige le troncon court-circuite')]
class CorrigerTronconWagramCommand extends Command
{
    private const CODE_EXTERNE_WAGRAM = '71423';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StationRepository $stationRepository,
        private readonly LigneRepository $ligneRepository,
        private readonly TypeTronconRepository $typeTronconRepository,
        private readonly TypeDesserteRepository $typeDesserteRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $wagram = $this->stationRepository->findOneBy(['codeExterne' => self::CODE_EXTERNE_WAGRAM]);
        if (null === $wagram) {
            $io->error(sprintf('Station introuvable (code_externe %s).', self::CODE_EXTERNE_WAGRAM));

            return Command::FAILURE;
        }

        $ligne3 = $this->ligneRepository->findOneBy(['label' => '3', 'typeTransport' => $this->trouverTypeTransportMetro()]);
        if (null === $ligne3) {
            $io->error('Ligne 3 (Métro) introuvable.');

            return Command::FAILURE;
        }

        foreach ($wagram->getDessertes() as $desserte) {
            if ($desserte->getLigne() === $ligne3) {
                $io->success('Desserte Wagram/Ligne 3 deja presente - rien a faire.');

                return Command::SUCCESS;
            }
        }

        $desserteMalesherbes = $this->trouverDesserteParLabel($ligne3, 'Malesherbes');
        $desservePereire = $this->trouverDesserteParLabel($ligne3, 'Pereire');
        if (null === $desserteMalesherbes || null === $desservePereire) {
            $io->error('Desserte Malesherbes ou Pereire introuvable sur la Ligne 3.');

            return Command::FAILURE;
        }

        $tronconDirect = $this->trouverTronconEntre($desserteMalesherbes, $desservePereire);
        if (null === $tronconDirect) {
            $io->error('Troncon direct Malesherbes<->Pereire introuvable (topologie deja modifiee ?).');

            return Command::FAILURE;
        }

        // Sauvegarde des 2 Mission existantes (une par sens) AVANT toute suppression : chaque
        // TronconDesserte de role "Depart" du troncon direct porte une Mission indiquant la
        // Direction (terminus) vers laquelle on circule en partant d'ici.
        $missionParDesserteDepart = [];
        foreach ($tronconDirect->getTronconDessertes() as $td) {
            if ('Départ' !== $td->getTypeDesserte()?->getLabel()) {
                continue;
            }
            foreach ($td->getMissions() as $mission) {
                $missionParDesserteDepart[spl_object_id($td->getDesserte())] = $mission;
            }
        }

        $desserteWagram = new Desserte();
        $desserteWagram->setStation($wagram);
        $desserteWagram->setLigne($ligne3);
        $this->entityManager->persist($desserteWagram);

        $typeInterieur = $this->typeTronconRepository->findOneBy(['label' => 'Interieur']);
        $typeDepart = $this->typeDesserteRepository->findOneBy(['label' => 'Départ']);
        $typeArrivee = $this->typeDesserteRepository->findOneBy(['label' => 'Arrivée']);

        $tdDepartsMalesherbesWagram = $this->creerTroncon($desserteMalesherbes, $desserteWagram, $typeInterieur, $typeDepart, $typeArrivee);
        $tdDepartsWagramPereire = $this->creerTroncon($desserteWagram, $desservePereire, $typeInterieur, $typeDepart, $typeArrivee);

        // Repointe la Mission "en partant de Malesherbes" vers le nouveau troncon Malesherbes<->Wagram.
        $missionMalesherbes = $missionParDesserteDepart[spl_object_id($desserteMalesherbes)] ?? null;
        $missionMalesherbes?->setTronconDesserte($tdDepartsMalesherbesWagram[spl_object_id($desserteMalesherbes)]);

        // Repointe la Mission "en partant de Pereire" vers le nouveau troncon Wagram<->Pereire.
        $missionPereire = $missionParDesserteDepart[spl_object_id($desservePereire)] ?? null;
        $missionPereire?->setTronconDesserte($tdDepartsWagramPereire[spl_object_id($desservePereire)]);

        // Nouvelles Mission pour les 2 TronconDesserte-Depart de Wagram (meme numero/service que
        // la Mission du sens correspondant, direction opposee a chaque fois).
        if (null !== $missionPereire) {
            $this->creerMissionCopiee($missionPereire, $tdDepartsMalesherbesWagram[spl_object_id($desserteWagram)]);
        }
        if (null !== $missionMalesherbes) {
            $this->creerMissionCopiee($missionMalesherbes, $tdDepartsWagramPereire[spl_object_id($desserteWagram)]);
        }

        foreach ($tronconDirect->getTronconDessertes() as $tronconDesserte) {
            $this->entityManager->remove($tronconDesserte);
        }
        $this->entityManager->remove($tronconDirect);

        $this->entityManager->flush();

        $io->success('Desserte Wagram/Ligne 3 creee, troncon direct Malesherbes<->Pereire remplace par Malesherbes<->Wagram<->Pereire (Mission repointees/creees).');

        return Command::SUCCESS;
    }

    private function trouverTypeTransportMetro(): ?object
    {
        return $this->entityManager->getRepository(\App\Entity\TypeTransport::class)->findOneBy(['label' => 'Métro']);
    }

    private function trouverDesserteParLabel(object $ligne, string $label): ?Desserte
    {
        foreach ($ligne->getDessertes() as $desserte) {
            if ($desserte->getStation()?->getLabel() === $label) {
                return $desserte;
            }
        }

        return null;
    }

    private function trouverTronconEntre(Desserte $a, Desserte $b): ?Troncon
    {
        foreach ($a->getTronconDessertes() as $td) {
            $troncon = $td->getTroncon();
            foreach ($troncon->getTronconDessertes() as $autreTd) {
                if ($autreTd->getDesserte() === $b) {
                    return $troncon;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, TronconDesserte> TronconDesserte de role "Depart" indexes par
     *                                      spl_object_id() de leur Desserte (a et b)
     */
    private function creerTroncon(Desserte $a, Desserte $b, ?object $typeInterieur, ?object $typeDepart, ?object $typeArrivee): array
    {
        $troncon = new Troncon();
        $troncon->setTypeTroncon($typeInterieur);
        $this->entityManager->persist($troncon);

        $tdDeparts = [];
        foreach ([[$a, $b], [$b, $a]] as [$depart, $arrivee]) {
            $tdDepart = new TronconDesserte();
            $tdDepart->setTroncon($troncon);
            $tdDepart->setDesserte($depart);
            $tdDepart->setTypeDesserte($typeDepart);
            $this->entityManager->persist($tdDepart);
            $tdDeparts[spl_object_id($depart)] = $tdDepart;

            $tdArrivee = new TronconDesserte();
            $tdArrivee->setTroncon($troncon);
            $tdArrivee->setDesserte($arrivee);
            $tdArrivee->setTypeDesserte($typeArrivee);
            $this->entityManager->persist($tdArrivee);
        }

        return $tdDeparts;
    }

    private function creerMissionCopiee(Mission $modele, TronconDesserte $tronconDesserte): void
    {
        $mission = new Mission();
        $mission->setNumero($modele->getNumero());
        $mission->setService($modele->getService());
        $mission->setDirection($modele->getDirection());
        $mission->setTronconDesserte($tronconDesserte);
        $this->entityManager->persist($mission);
    }
}
