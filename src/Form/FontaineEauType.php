<?php

namespace App\Form;

use App\Entity\Acces;
use App\Entity\FontaineEau;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FontaineEauType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ligneLabel', null, ['required' => false])
            ->add('label')
            ->add('adresse', null, ['required' => false])
            ->add('codePostal', null, ['required' => false])
            ->add('commune', null, ['required' => false])
            ->add('numeroAccesProche', null, ['required' => false])
            ->add('nomAccesProche', null, ['required' => false])
            ->add('enZoneControlee', null, ['required' => false])
            ->add('identifiantRatp', null, ['required' => false])
            ->add('latitude')
            ->add('longitude')
            ->add('acces', EntityType::class, [
                'class' => Acces::class,
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => '-- Aucun --',
            ])
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
            'data_class' => FontaineEau::class,
        ]);
    }
}
