<?php

namespace App\Form;

use App\Entity\Raison;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RaisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('stations', EntityType::class, [
                'class' => Station::class,
                'choice_label' => 'label',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'help' => "Choisir une station l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Raison::class,
        ]);
    }
}
