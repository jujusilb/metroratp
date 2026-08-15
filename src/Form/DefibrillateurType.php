<?php

namespace App\Form;

use App\Entity\Defibrillateur;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DefibrillateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('localisation')
            ->add('codePostal', null, ['required' => false])
            ->add('ville', null, ['required' => false])
            ->add('acces', null, ['required' => false])
            ->add('accesLibre', null, ['required' => false])
            ->add('complementLocalisation', null, ['required' => false])
            ->add('disponibiliteSemaine', null, ['required' => false])
            ->add('disponibiliteHoraires', null, ['required' => false])
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
            'data_class' => Defibrillateur::class,
        ]);
    }
}
