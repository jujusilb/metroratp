<?php

namespace App\Form;

use App\Entity\Depot;
use App\Entity\Ligne;
use App\Entity\Ville;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DepotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('adresse', null, ['required' => false])
            ->add('ville', EntityType::class, [
                'class' => Ville::class,
                'choice_label' => 'label',
                'required' => false,
            ])
            ->add('lignes', EntityType::class, [
                'class' => Ligne::class,
                'choice_label' => 'label',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'help' => "Choisir une ligne l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Depot::class,
        ]);
    }
}
