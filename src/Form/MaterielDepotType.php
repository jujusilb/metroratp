<?php

namespace App\Form;

use App\Entity\Depot;
use App\Entity\Materiel;
use App\Entity\MaterielDepot;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaterielDepotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('arrivee', null, ['required' => false])
            ->add('fin', null, ['required' => false])
            ->add('effectif', null, ['required' => false])
            ->add('effectifDate', null, ['required' => false])
            ->add('materiel', EntityType::class, [
                'class' => Materiel::class,
                'choice_label' => 'label',
            ])
            ->add('depot', EntityType::class, [
                'class' => Depot::class,
                'choice_label' => 'label',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MaterielDepot::class,
        ]);
    }
}
