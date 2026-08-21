<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe l'accessibilite, la signaletique sonore/visuelle et la climatisation officielles IDFM
 * par couple (Station, Ligne) depuis sdap-arrets-associes.csv - une ligne par (route_id, stop_id),
 * bien plus precis que le referentiel ArT seul (arrets-transporteur.csv) qui n'a aucune notion de
 * ligne. La climatisation vient du champ Extensions (JSON imbrique,
 * ServiceFacilitySet.ClimateControlList) - seul le champ bookingRules du meme dataset reste
 * inexploite (51/36695 lignes seulement, non significatif).
 *
 * Rattachement 100% par identifiants officiels, aucune proximite geographique :
 * - route_id (ex "IDFM:C01100") -> Ligne::codeExterne (le prefixe "IDFM:" retire) ;
 * - stop_id (ex "IDFM:27098") -> ArRId (prefixe retire) -> relations.csv -> ZdCId -> Station::codeExterne.
 * Verifie avant d'implementer : 35005/36695 lignes (95%) chainent jusqu'a une Desserte(Station,Ligne)
 * deja existante en base.
 *
 * Pourquoi sur Desserte et pas Station : l'accessibilite/signaletique depend du materiel roulant et
 * du service de CETTE ligne precise a cet arret (un bus a plancher bas sur une ligne peut etre
 * accessible pendant qu'une autre ligne au meme arret physique de bus ne l'est pas), donc reste
 * propre a chaque couple (Station, Ligne) plutot qu'un seul booleen partage pour tout le lieu -
 * contrairement au mobilier physique (banc, abri...), qui lui est bien partage par toutes les
 * lignes d'un meme arret bus (voir EquipementArret, referencee depuis Desserte::equipementArret).
 */
#[AsCommand(name: 'app:importer-accessibilite-dessertes', description: "Importe Desserte::estAccessible/signalisationSonore/signalisationVisuelle depuis sdap-arrets-associes.csv")]
class ImporterAccessibiliteDessertesCommand extends Command
{
    private const SDAP_CSV = 'documentation/scripts/donnees-extraites/sdap-arrets-associes.csv';
    private const RELATIONS_CSV = 'documentation/scripts/donnees-extraites/relations.csv';

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
        $header = fgetcsv($fichier, separator: ';');
        $header[0] = preg_replace('/^\x{FEFF}+/u', '', $header[0]);
        while (false !== ($ligne = fgetcsv($fichier, separator: ';'))) {
            yield array_combine($header, $ligne);
        }
        fclose($fichier);
    }

    /**
     * "true" -> true, "false" -> false, "unknown"/"partial" -> null (information inconnue ou trop
     * ambigue pour trancher, pas une absence constatee).
     */
    private function versBool(string $valeur): ?bool
    {
        return match ($valeur) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * Extrait Extensions.ServiceFacilitySet.ClimateControlList (JSON imbrique en texte dans une
     * colonne CSV) plutot que de parser tout le JSON pour une seule valeur - regex volontairement
     * simple, verifiee sur les 36695 lignes (aucune valeur avec guillemet echappe rencontree).
     * 'unknown' -> null (information inconnue, pas "non climatise").
     */
    private function versClimatisation(string $extensions): ?string
    {
        if (!preg_match('/"ClimateControlList":\s*"([^"]*)"/', $extensions, $matches)) {
            return null;
        }

        return match ($matches[1]) {
            'airConditioning' => 'Climatisé',
            'noConditioning' => 'Non climatisé',
            'other' => 'Autre',
            default => null,
        };
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Lecture de relations.csv (ArRId => ZdCId)...');
        $zdcIdParArrId = [];
        foreach ($this->lireCsv(self::RELATIONS_CSV) as $ligne) {
            if ('' === $ligne['ArRId'] || '' === $ligne['ZdCId']) {
                continue;
            }
            $zdcIdParArrId[$ligne['ArRId']] = $ligne['ZdCId'];
        }

        $ligneIdParCode = [];
        foreach ($connexion->executeQuery('SELECT code_externe, id FROM ligne WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $ligneIdParCode[$row['code_externe']] = (int) $row['id'];
        }
        $stationIdParCode = [];
        foreach ($connexion->executeQuery('SELECT code_externe, id FROM station WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $stationIdParCode[$row['code_externe']] = (int) $row['id'];
        }
        $desserteIdParPaire = [];
        foreach ($connexion->executeQuery('SELECT id, station_id, ligne_id FROM desserte')->iterateAssociative() as $row) {
            $desserteIdParPaire[$row['station_id'].'|'.$row['ligne_id']] = (int) $row['id'];
        }

        $io->section('Import de '.self::SDAP_CSV.'...');
        $nbTotal = 0;
        $nbMaj = 0;
        $nbSansDesserte = 0;
        foreach ($this->lireCsv(self::SDAP_CSV) as $ligne) {
            ++$nbTotal;
            $ligneId = $ligneIdParCode[str_replace('IDFM:', '', $ligne['route_id'])] ?? null;
            $arrId = str_replace('IDFM:', '', $ligne['stop_id']);
            $zdcId = $zdcIdParArrId[$arrId] ?? null;
            $stationId = null !== $zdcId ? ($stationIdParCode[$zdcId] ?? null) : null;

            $desserteId = (null !== $ligneId && null !== $stationId) ? ($desserteIdParPaire[$stationId.'|'.$ligneId] ?? null) : null;
            if (null === $desserteId) {
                ++$nbSansDesserte;
                continue;
            }

            $connexion->executeStatement(
                'UPDATE desserte SET est_accessible = ?, signalisation_sonore = ?, signalisation_visuelle = ?, climatisation = ? WHERE id = ?',
                [
                    $this->versBool($ligne['ArRAccessibility']),
                    $this->versBool($ligne['ArRAudibleSignals']),
                    $this->versBool($ligne['ArRVisualSigns']),
                    $this->versClimatisation($ligne['Extensions']),
                    $desserteId,
                ],
            );
            ++$nbMaj;
        }

        $io->success(sprintf('%d Desserte mises a jour sur %d lignes lues (%d sans Desserte correspondante).', $nbMaj, $nbTotal, $nbSansDesserte));

        return Command::SUCCESS;
    }
}
