<?php

namespace App\Form;

use App\Entity\Depot;
use App\Entity\DepotGestionnaire;
use App\Entity\Gestionnaire;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DepotGestionnaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('arrivee', null, ['required' => false])
            ->add('fin', null, ['required' => false])
            ->add('depot', EntityType::class, [
                'class' => Depot::class,
                'choice_label' => 'label',
            ])
            ->add('gestionnaire', EntityType::class, [
                'class' => Gestionnaire::class,
                'choice_label' => 'label',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DepotGestionnaire::class,
        ]);
    }
}
