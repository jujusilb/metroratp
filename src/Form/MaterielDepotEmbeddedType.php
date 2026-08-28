<?php

namespace App\Form;

use App\Entity\Materiel;
use App\Entity\MaterielDepot;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Version "imbriquee" de MaterielDepotType, sans le champ depot (implicite) - utilisee dans le
 * CollectionType de DepotType, pas de page CRUD separee (voir TODO.md).
 */
class MaterielDepotEmbeddedType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('materiel', EntityType::class, [
                'class' => Materiel::class,
                'choice_label' => 'label',
            ])
            ->add('arrivee', null, ['required' => false])
            ->add('fin', null, ['required' => false])
            ->add('effectif', null, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MaterielDepot::class,
        ]);
    }
}
