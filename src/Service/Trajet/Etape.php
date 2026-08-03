<?php

namespace App\Service\Trajet;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use App\Entity\Troncon;

/**
 * Un segment du trajet calcule : soit on reste sur la meme ligne (un troncon), soit on change
 * de ligne a la meme station (une correspondance).
 */
final class Etape
{
    public const TYPE_TRONCON = 'troncon';
    public const TYPE_CORRESPONDANCE = 'correspondance';

    public function __construct(
        public readonly Desserte $depart,
        public readonly Desserte $arrivee,
        public readonly string $type,
        public readonly float $dureeMinutes,
        public readonly ?Troncon $troncon = null,
        public readonly ?Correspondance $correspondance = null,
    ) {
    }
}
