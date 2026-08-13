<?php

namespace App\Form;

use App\Entity\Acces;
use App\Entity\Ligne;
use App\Entity\PositionRame;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PositionRameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ligne', EntityType::class, [
                'class' => Ligne::class,
                'choice_label' => 'label',
            ])
            ->add('station', EntityType::class, [
                'class' => Station::class,
                'choice_label' => 'label',
            ])
            ->add('destination')
            ->add('acces', EntityType::class, [
                'class' => Acces::class,
                'choice_label' => 'label',
                'required' => false,
            ])
            ->add('labelPosition')
            ->add('position')
            ->add('positionMax')
            ->add('equipement', null, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PositionRame::class,
        ]);
    }
}
