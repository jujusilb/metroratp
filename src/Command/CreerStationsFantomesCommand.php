<?php

namespace App\Command;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\PeriodeOuverture;
use App\Entity\Raison;
use App\Entity\Station;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Catalogue les "stations fantomes" du metro de Paris (demande utilisateur, voir TODO.md) : 4
 * fermees definitivement en 1939 (Arsenal L5, Champ de Mars L8, Croix-Rouge L10, Saint-Martin
 * L8+L9), 1 absorbee par une station voisine en 1969 (Martin Nadaud L3 -> Gambetta), 2 jamais
 * ouvertes au public (Porte Molitor L9+L10, Haxo L3bis+L7bis). Sourcees individuellement sur
 * Wikipedia (une page dediee par station), aucune date devinee.
 *
 * Remarque utilisateur importante ayant guide le modele : Martin Nadaud/Porte Molitor/Haxo
 * existent DEJA en base comme de vrais arrets de BUS actifs aujourd'hui (meme lieu, service
 * different) - inactiver la Station entiere serait donc faux. L'inactivite se marque sur la
 * Desserte precise (Station x Ligne) plutot que sur la Station, via Raison::dessertes (nouveau,
 * cf. migration Version20260829100000) : une Station peut avoir des Desserte actives et
 * inactives simultanement.
 *
 * Idempotente : ne recree rien si la Desserte "fantome" existe deja pour la paire (Station, Ligne).
 */
#[AsCommand(name: 'app:creer-stations-fantomes', description: 'Catalogue les stations de metro parisien fermees definitivement ou jamais ouvertes au public')]
class CreerStationsFantomesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $raisonFermeture1939 = $this->trouverOuCreerRaison('Fermeture définitive (1939, Seconde Guerre mondiale)');
        $raisonJamaisOuverte = $this->trouverOuCreerRaison('Jamais mise en service (accès jamais construits)');
        $raisonAbsorbee = $this->trouverOuCreerRaison('Absorbée par une station voisine lors d\'une réorganisation du réseau');
        $this->entityManager->flush();

        $ligneRepository = $this->entityManager->getRepository(Ligne::class);
        $ligne = static fn (string $label): Ligne => $ligneRepository->findOneBy(['label' => $label]) ?? throw new \RuntimeException("Ligne '$label' introuvable");

        $nbCreees = 0;

        // 4 stations n'existant pas du tout en base (fermees en 1939, aucun autre service depuis).
        $nbCreees += $this->creerDesserteFantome(
            $this->trouverOuCreerStation('Arsenal', 'Paris 4e', 48.849241, 2.368222),
            $ligne('5'),
            $raisonFermeture1939,
            new \DateTime('1906-12-17'),
            new \DateTime('1939-09-02'),
        );
        $nbCreees += $this->creerDesserteFantome(
            $this->trouverOuCreerStation('Champ de Mars', 'Paris 7e', 48.851819, 2.302035),
            $ligne('8'),
            $raisonFermeture1939,
            new \DateTime('1913-07-13'),
            new \DateTime('1939-09-02'),
        );
        $nbCreees += $this->creerDesserteFantome(
            $this->trouverOuCreerStation('Croix-Rouge', 'Paris 6e', 48.852196, 2.331594),
            $ligne('10'),
            $raisonFermeture1939,
            new \DateTime('1923-12-30'),
            new \DateTime('1939-09-02'),
        );
        $saintMartin = $this->trouverOuCreerStation('Saint-Martin', 'Paris 3e', 48.868373, 2.358421);
        $nbCreees += $this->creerDesserteFantome($saintMartin, $ligne('8'), $raisonFermeture1939, new \DateTime('1931-05-05'), new \DateTime('1939-09-02'));
        $nbCreees += $this->creerDesserteFantome($saintMartin, $ligne('9'), $raisonFermeture1939, new \DateTime('1931-05-05'), new \DateTime('1939-09-02'));

        // 3 stations existant deja (arrets de bus actifs) : Desserte metro fantome ajoutee dessus,
        // Station elle-meme non touchee (voir docblock de la classe).
        $martinNadaud = $this->trouverStationExistante('Martin Nadaud');
        $nbCreees += $this->creerDesserteFantome($martinNadaud, $ligne('3'), $raisonAbsorbee, new \DateTime('1905-01-25'), new \DateTime('1969-08-23'));

        $porteMolitor = $this->trouverStationExistante('Porte Molitor');
        $nbCreees += $this->creerDesserteFantome($porteMolitor, $ligne('9'), $raisonJamaisOuverte, null, null);
        $nbCreees += $this->creerDesserteFantome($porteMolitor, $ligne('10'), $raisonJamaisOuverte, null, null);

        $haxo = $this->trouverStationExistante('Haxo');
        $nbCreees += $this->creerDesserteFantome($haxo, $ligne('3b'), $raisonJamaisOuverte, null, null);
        $nbCreees += $this->creerDesserteFantome($haxo, $ligne('7b'), $raisonJamaisOuverte, null, null);

        $this->entityManager->flush();

        $io->success("$nbCreees Desserte fantome(s) creee(s) (les autres existaient deja - commande idempotente).");

        return Command::SUCCESS;
    }

    private function trouverOuCreerRaison(string $label): Raison
    {
        $existante = $this->entityManager->getRepository(Raison::class)->findOneBy(['label' => $label]);
        if (null !== $existante) {
            return $existante;
        }

        $raison = new Raison();
        $raison->setLabel($label);
        $this->entityManager->persist($raison);

        return $raison;
    }

    private function trouverOuCreerStation(string $label, string $ville, float $latitude, float $longitude): Station
    {
        $existante = $this->entityManager->getRepository(Station::class)->findOneBy(['label' => $label, 'ville' => $ville]);
        if (null !== $existante) {
            return $existante;
        }

        $station = new Station();
        $station->setLabel($label);
        $station->setVille($ville);
        $station->setLatitude($latitude);
        $station->setLongitude($longitude);
        $this->entityManager->persist($station);
        $this->entityManager->flush();

        return $station;
    }

    private function trouverStationExistante(string $label): Station
    {
        return $this->entityManager->getRepository(Station::class)->findOneBy(['label' => $label])
            ?? throw new \RuntimeException("Station '$label' introuvable - attendue deja presente en base (arret de bus).");
    }

    /**
     * Cree la Desserte (Station, Ligne) si elle n'existe pas deja, avec sa PeriodeOuverture
     * (si les dates sont connues) et sa Raison d'inactivite. Idempotente.
     */
    private function creerDesserteFantome(Station $station, Ligne $ligne, Raison $raison, ?\DateTime $ouverture, ?\DateTime $fermeture): int
    {
        $existante = $this->entityManager->getRepository(Desserte::class)->findOneBy(['station' => $station, 'ligne' => $ligne]);
        if (null !== $existante) {
            return 0;
        }

        $desserte = new Desserte();
        $desserte->setStation($station);
        $desserte->setLigne($ligne);
        $this->entityManager->persist($desserte);

        if (null !== $ouverture || null !== $fermeture) {
            $periode = new PeriodeOuverture();
            $periode->setDesserte($desserte);
            $periode->setOrdre(1);
            $periode->setOuverture($ouverture);
            $periode->setFermeture($fermeture);
            $this->entityManager->persist($periode);
        }

        $raison->addDesserte($desserte);

        return 1;
    }
}
