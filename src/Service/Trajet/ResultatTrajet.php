<?php

namespace App\Service\Trajet;

/**
 * Le resultat du calcul de plus court chemin : la liste ordonnee des etapes, et la duree
 * totale estimee (somme des poids, en minutes).
 */
final class ResultatTrajet
{
    /**
     * @param Etape[] $etapes
     */
    public function __construct(
        public readonly array $etapes,
        public readonly float $dureeMinutesTotale,
    ) {
    }

    public function getNombreCorrespondances(): int
    {
        return count(array_filter(
            $this->etapes,
            static fn (Etape $e): bool => Etape::TYPE_CORRESPONDANCE === $e->type
        ));
    }
}
