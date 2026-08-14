<?php

namespace App\Form;

use App\Entity\Plan;
use App\Entity\PoleEchange;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('plan', EntityType::class, [
                'class' => Plan::class,
                'choice_label' => 'secteur',
                'required' => false,
                'placeholder' => '-- Aucun --',
            ])
            ->add('poleEchange', EntityType::class, [
                'class' => PoleEchange::class,
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => '-- Aucun --',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Station::class,
        ]);
    }
}
