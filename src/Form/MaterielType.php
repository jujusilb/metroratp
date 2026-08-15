<?php

namespace App\Form;

use App\Entity\Materiel;
use App\Entity\TypeMateriel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaterielType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('anneeProduction')
            ->add('constructeur', null, ['required' => false])
            ->add('vitesseMaxKmh', null, ['required' => false])
            ->add('typeMateriel', EntityType::class, [
                'class' => TypeMateriel::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Materiel::class,
        ]);
    }
}
