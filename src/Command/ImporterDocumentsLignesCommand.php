<?php

namespace App\Command;

use App\Entity\DocumentLigne;
use App\Entity\Ligne;
use App\Repository\DocumentLigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les DocumentLigne (fiches horaires + plans PDF officiels, dataset IDFM
 * "fiches-horaires-et-plans", 4507 documents) depuis fiches-horaires-et-plans.csv.
 *
 * Rattachement par codeExterne (ID_Line) quand possible, avec repli par UPPER(label) - meme
 * pattern que app:importer-traces-lignes, necessaire car le codeExterne des Ligne de metro est
 * connu pour etre incoherent avec le referentiel IDFM actuel (voir TODO.md). Verifie : le repli
 * par label n'ameliore que marginalement la couverture ici (~20 lignes supplementaires) - la
 * majorite des ~1269 documents non rattaches correspondent a des Ligne absentes de notre base
 * (operateurs/lignes non couverts par l'import du reseau), pas au probleme de codeExterne.
 *
 * Deduplique par URL (cle naturelle la plus fiable ici, le CSV source n'a pas d'identifiant par
 * document) : 57 doublons exacts observes sur 4507 lignes, ignores silencieusement.
 */
#[AsCommand(name: 'app:importer-documents-lignes', description: 'Importe les fiches horaires et plans PDF officiels par Ligne')]
class ImporterDocumentsLignesCommand extends Command
{
    private const DOCUMENTS_CSV = 'documentation/scripts/donnees-extraites/fiches-horaires-et-plans.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentLigneRepository $documentLigneRepository,
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connexion = $this->entityManager->getConnection();

        $io->section('Chargement des Lignes (par codeExterne, avec repli par label pour le metro)...');
        $ligneIdParCode = [];
        foreach ($connexion->executeQuery('SELECT code_externe, id FROM ligne WHERE code_externe IS NOT NULL')->iterateAssociative() as $row) {
            $ligneIdParCode[$row['code_externe']] = (int) $row['id'];
        }
        $ligneIdParLabel = [];
        foreach ($connexion->executeQuery('SELECT UPPER(label) AS label, id, code_externe FROM ligne ORDER BY (code_externe IS NULL) DESC')->iterateAssociative() as $row) {
            if (!isset($ligneIdParLabel[$row['label']])) {
                $ligneIdParLabel[$row['label']] = (int) $row['id'];
            }
        }
        $io->info(sprintf('%d Lignes avec codeExterne, %d labels de Ligne.', \count($ligneIdParCode), \count($ligneIdParLabel)));

        $io->section('Lecture de fiches-horaires-et-plans.csv et import des DocumentLigne...');
        $nbCrees = 0;
        $nbMaj = 0;
        $nbIgnores = 0;
        $urlsVues = [];

        foreach ($this->lireCsv(self::DOCUMENTS_CSV) as $ligne) {
            $url = $ligne['URL'];
            if (isset($urlsVues[$url])) {
                continue;
            }
            $urlsVues[$url] = true;

            $ligneId = $ligneIdParCode[$ligne['ID_Line']] ?? $ligneIdParLabel[mb_strtoupper($ligne['Name_Line'])] ?? null;
            if (null === $ligneId) {
                ++$nbIgnores;
                continue;
            }

            $document = $this->documentLigneRepository->trouverParUrl($url) ?? new DocumentLigne();
            $estNouveau = null === $document->getId();

            $document->setLigne($this->entityManager->getReference(Ligne::class, $ligneId));
            $document->setType($ligne['Type']);
            $document->setNom('' !== $ligne['Document_Name'] ? $ligne['Document_Name'] : $ligne['Type']);
            $document->setUrl($url);

            if ($estNouveau) {
                $this->entityManager->persist($document);
                ++$nbCrees;
            } else {
                ++$nbMaj;
            }
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%d DocumentLigne crees, %d mis a jour (%d ignores : Ligne introuvable).',
            $nbCrees,
            $nbMaj,
            $nbIgnores,
        ));

        return Command::SUCCESS;
    }
}
