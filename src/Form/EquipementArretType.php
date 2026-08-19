<?php

namespace App\Form;

use App\Entity\EquipementArret;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EquipementArretType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('artId')
            ->add('nom')
            ->add('ville', null, ['required' => false])
            ->add('latitude')
            ->add('longitude')
            ->add('accessibleFauteuilRoulant', null, ['required' => false])
            ->add('banc', null, ['required' => false])
            ->add('poubelle', null, ['required' => false])
            ->add('eclairage', null, ['required' => false])
            ->add('abri', null, ['required' => false])
            ->add('bandeTactile', null, ['required' => false])
            ->add('distanceReferentielOsm', null, ['required' => false])
            ->add('station', EntityType::class, [
                'class' => Station::class,
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => '-- Aucune --',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EquipementArret::class,
        ]);
    }
}
