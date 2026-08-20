<?php

namespace App\Form;

use App\Entity\ArretTransporteur;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArretTransporteurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('artId')
            ->add('nom')
            ->add('ville', null, ['required' => false])
            ->add('type')
            ->add('zoneTarifaire', null, ['required' => false])
            ->add('estAccessible', null, ['required' => false])
            ->add('signalisationSonore', null, ['required' => false])
            ->add('signalisationVisuelle', null, ['required' => false])
            ->add('latitude')
            ->add('longitude')
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
            'data_class' => ArretTransporteur::class,
        ]);
    }
}
