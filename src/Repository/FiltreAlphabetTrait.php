<?php

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;

/**
 * Filtre alphabetique (barre A-Z) + recherche texte, reutilisable sur n'importe quel index pagine
 * ayant un champ texte pertinent a trier (label, nom...). Voir templates/tools/filtre_alphabet.html.twig
 * pour la barre affichee cote vue.
 */
trait FiltreAlphabetTrait
{
    public function appliquerFiltreAlphabetEtRecherche(QueryBuilder $qb, string $champ, ?string $lettre, ?string $recherche): QueryBuilder
    {
        if (null !== $lettre && '' !== $lettre) {
            $qb->andWhere($champ.' LIKE :lettre')
                ->setParameter('lettre', $lettre.'%')
            ;
        }

        if (null !== $recherche && '' !== trim($recherche)) {
            $qb->andWhere($champ.' LIKE :recherche')
                ->setParameter('recherche', '%'.trim($recherche).'%')
            ;
        }

        return $qb;
    }
}
