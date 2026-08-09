<?php

namespace App\Form;

use App\Entity\Ligne;
use App\Entity\Materiel;
use App\Entity\MaterielLigne;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaterielLigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('arrivee')
            ->add('fin')
            ->add('effectif', null, ['required' => false])
            ->add('effectifDate', null, ['required' => false])
            ->add('materiel', EntityType::class, [
                'class' => Materiel::class,
                'choice_label' => 'id',
            ])
            ->add('ligne', EntityType::class, [
                'class' => Ligne::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MaterielLigne::class,
        ]);
    }
}
