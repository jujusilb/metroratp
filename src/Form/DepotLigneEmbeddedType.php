<?php

namespace App\Form;

use App\Entity\DepotLigne;
use App\Entity\Ligne;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Version "imbriquee" de DepotLigneType, sans le champ depot (implicite) - utilisee dans le
 * CollectionType de DepotType, pas de page CRUD separee (voir TODO.md).
 */
class DepotLigneEmbeddedType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ligne', EntityType::class, [
                'class' => Ligne::class,
                'choice_label' => 'label',
            ])
            ->add('arrivee', null, ['required' => false])
            ->add('fin', null, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DepotLigne::class,
        ]);
    }
}
