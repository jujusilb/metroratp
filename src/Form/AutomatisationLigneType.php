<?php

namespace App\Form;

use App\Entity\Automatisation;
use App\Entity\AutomatisationLigne;
use App\Entity\Ligne;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AutomatisationLigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('automatisation', EntityType::class, [
                'class' => Automatisation::class,
                'choice_label' => 'label',
            ])
            ->add('ligne', EntityType::class, [
                'class' => Ligne::class,
                'choice_label' => 'label',
            ])
            ->add('dateDeMiseEnPlace', null, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AutomatisationLigne::class,
        ]);
    }
}
