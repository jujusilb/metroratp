<?php

namespace App\Form;

use App\Entity\TypeDesserte;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * "Départ"/"Arrivée" sont compares en dur (chaine, pas par id) dans une quinzaine d'endroits du
 * projet - entites (Desserte, Troncon, Ligne, Mission), plusieurs Command d'import de topologie,
 * et surtout TrajetFinder::construireGraphe() (SQL brut du calculateur de trajet lui-meme :
 * "type_desserte.label = 'Départ'"). Renommer l'un de ces 2 libellés casserait silencieusement
 * tout ça. Le label de ces 2 lignes precises est donc verrouille (champ desactive) - une
 * nouvelle valeur de TypeDesserte, elle, reste librement nommable (aucun code ne s'y refere).
 */
class TypeDesserteType extends AbstractType
{
    public const LABELS_VERROUILLES = ['Départ', 'Arrivée'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ?TypeDesserte $typeDesserte */
        $typeDesserte = $options['data'] ?? null;
        $estVerrouille = null !== $typeDesserte && \in_array($typeDesserte->getLabel(), self::LABELS_VERROUILLES, true);

        $builder
            ->add('label', null, [
                'disabled' => $estVerrouille,
                'help' => $estVerrouille
                    ? 'Libellé verrouillé : utilisé directement (en dur) par le calculateur de trajet et plusieurs imports de topologie.'
                    : null,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TypeDesserte::class,
        ]);
    }
}
