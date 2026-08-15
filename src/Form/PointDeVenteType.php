<?php

namespace App\Form;

use App\Entity\PointDeVente;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PointDeVenteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('codeExterne')
            ->add('label')
            ->add('type', null, ['required' => false])
            ->add('adresse', null, ['required' => false])
            ->add('codePostal', null, ['required' => false])
            ->add('ville', null, ['required' => false])
            ->add('horaires', null, ['required' => false])
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
            'data_class' => PointDeVente::class,
        ]);
    }
}
