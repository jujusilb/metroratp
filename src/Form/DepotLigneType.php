<?php

namespace App\Form;

use App\Entity\Depot;
use App\Entity\DepotLigne;
use App\Entity\Ligne;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DepotLigneType extends AbstractType
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
            ->add('ligne', EntityType::class, [
                'class' => Ligne::class,
                'choice_label' => 'label',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DepotLigne::class,
        ]);
    }
}
