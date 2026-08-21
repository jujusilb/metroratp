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
 * Complete la topologie du RER D avec les aretes du maillage Evry/Corbeil/Juvisy, volontairement
 * exclues de ConstruireTopologieRerCommand::construireRerD() (modele Direction/tronçon = un arbre,
 * ne peut pas representer un graphe a cycles - voir documentation/TODO.md, section "Lignes a
 * embranchements complexes"). Decouvert le 2026-08-21 en creusant les 28 Desserte de la ligne D
 * encore isolees (sans aucun Troncon) : ce sont exactement les stations de ce maillage, deja
 * presentes dans troncons_rer.csv (meme source que le reste de la ligne), simplement jamais
 * importees a cause de cette limite de modele.
 *
 * Volontairement PAS de Direction/Mission ici (contrairement a ConstruireTopologieRerCommand) :
 * TrajetFinder::construireGraphe() ne lit que Troncon/TronconDesserte (verifie - aucune reference
 * a Direction/Mission dans sa requete SQL), donc ces deux tables suffisent a rendre le maillage
 * utilisable par le calculateur de trajet, sans avoir a resoudre le probleme plus dur (et non
 * demande ici) de representer un cycle dans un modele pense pour un arbre.
 *
 * Idempotent : relit tout troncons_rer.csv pour la ligne D (pas seulement les aretes du maillage)
 * mais ne cree que celles qui manquent encore (verifie par paire de Desserte), donc rejouable sans
 * doublon meme apres un futur ConstruireTopologieRerCommand.
 */
#[AsCommand(name: 'app:construire-maillage-rer-d', description: 'Complete la topologie du RER D avec les aretes du maillage Evry/Corbeil/Juvisy (Troncon seul, sans Direction/Mission)')]
class ConstruireMaillageRerDCommand extends Command
{
    private const TRONCONS_CSV = 'documentation/scripts/donnees-extraites/troncons_rer.csv';
    private const CODE_EXTERNE_LIGNE_D = 'C01728';

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

        $ligne = $this->ligneRepository->findOneBy(['codeExterne' => self::CODE_EXTERNE_LIGNE_D]);
        if (null === $ligne) {
            $io->error('Ligne D introuvable (codeExterne '.self::CODE_EXTERNE_LIGNE_D.').');

            return Command::FAILURE;
        }

        /** @var array<string, Desserte> $dessertesParLabel */
        $dessertesParLabel = [];
        foreach ($ligne->getDessertes() as $desserte) {
            $label = $desserte->getStation()?->getLabel();
            if (null !== $label) {
                $dessertesParLabel[$label] = $desserte;
            }
        }

        /** @var array<int, true> $paireDejaExistante cle = min(id)."|".max(id) des Desserte deja reliees */
        $paireDejaExistante = $this->chargerPairesExistantes($ligne);

        $fichier = fopen(self::TRONCONS_CSV, 'r');
        fgetcsv($fichier); // en-tete
        $nbCrees = 0;
        $nbDejaLa = 0;
        $nbIgnores = 0;
        while (false !== ($ligneCsv = fgetcsv($fichier))) {
            [$routeLabel, , , $nomA, $nomB, $dureeMediane] = $ligneCsv;
            if ('D' !== $routeLabel) {
                continue;
            }

            $desserteA = $dessertesParLabel[$nomA] ?? null;
            $desserteB = $dessertesParLabel[$nomB] ?? null;
            if (null === $desserteA || null === $desserteB) {
                $io->warning(sprintf('Station "%s" ou "%s" introuvable parmi les dessertes de la ligne D, arete ignoree.', $nomA, $nomB));
                ++$nbIgnores;
                continue;
            }

            $cle = $this->clePaire($desserteA, $desserteB);
            if (isset($paireDejaExistante[$cle])) {
                ++$nbDejaLa;
                continue;
            }

            $duree = '' !== $dureeMediane ? (int) $dureeMediane : null;
            $this->creerTronconBidirectionnel($desserteA, $desserteB, $duree);
            $paireDejaExistante[$cle] = true;
            ++$nbCrees;
        }
        fclose($fichier);

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d troncons crees, %d deja presents, %d ignores (station introuvable).',
            $nbCrees,
            $nbDejaLa,
            $nbIgnores,
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function chargerPairesExistantes(Ligne $ligne): array
    {
        $connexion = $this->entityManager->getConnection();
        $paires = [];
        foreach ($connexion->executeQuery(
            <<<'SQL'
                SELECT tda.desserte_id AS a, tdb.desserte_id AS b
                FROM troncon_desserte tda
                JOIN type_desserte ttypeA ON ttypeA.id = tda.type_desserte_id AND ttypeA.label = 'Départ'
                JOIN troncon_desserte tdb ON tdb.troncon_id = tda.troncon_id AND tdb.desserte_id != tda.desserte_id
                JOIN type_desserte ttypeB ON ttypeB.id = tdb.type_desserte_id AND ttypeB.label = 'Arrivée'
                JOIN desserte d ON d.id = tda.desserte_id
                WHERE d.ligne_id = :ligneId
                SQL,
            ['ligneId' => $ligne->getId()]
        )->iterateAssociative() as $row) {
            $a = (int) $row['a'];
            $b = (int) $row['b'];
            $cle = min($a, $b).'|'.max($a, $b);
            $paires[$cle] = true;
        }

        return $paires;
    }

    private function clePaire(Desserte $a, Desserte $b): string
    {
        $idA = $a->getId();
        $idB = $b->getId();

        return min($idA, $idB).'|'.max($idA, $idB);
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
