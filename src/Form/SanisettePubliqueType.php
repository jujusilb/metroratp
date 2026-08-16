<?php

namespace App\Form;

use App\Entity\SanisettePublique;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SanisettePubliqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('arrondissement', null, ['required' => false])
            ->add('type')
            ->add('statut')
            ->add('adresse')
            ->add('horaire', null, ['required' => false])
            ->add('accesPmr', null, ['required' => false])
            ->add('relaisBebe', null, ['required' => false])
            ->add('urlFicheEquipement', null, ['required' => false])
            ->add('latitude')
            ->add('longitude')
            ->add('gestionnaire', null, ['required' => false])
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
            'data_class' => SanisettePublique::class,
        ]);
    }
}
