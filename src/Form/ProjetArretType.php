<?php

namespace App\Form;

use App\Entity\ProjetArret;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjetArretType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('nomProjet')
            ->add('operation', null, ['required' => false])
            ->add('nature', null, ['required' => false])
            ->add('mode', null, ['required' => false])
            ->add('statut', null, ['required' => false])
            ->add('phase', null, ['required' => false])
            ->add('creation')
            ->add('prolongement')
            ->add('amelioration')
            ->add('terminus')
            ->add('latitude')
            ->add('longitude')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjetArret::class,
        ]);
    }
}
