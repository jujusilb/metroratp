<?php

namespace App\Form;

use App\Entity\Acces;
use App\Entity\Station;
use App\Entity\StyleAcces;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AccesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('numero')
            ->add('isAccessible')
            ->add('styleAcces', EntityType::class, [
                'class' => StyleAcces::class,
                'label' => "Style d'accès",
                // Sans choice_label, Symfony tente (string) $styleAcces pour l'afficher - StyleAcces
                // n'a pas de __toString() : plantage garanti des qu'au moins une ligne existe (10
                // aujourd'hui en base, trouve en verifiant un risque de plantage memoire pour une
                // autre relation, voir TODO.md audit CRUD).
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => '-- Aucun --',
            ])
            // Acces est le cote proprietaire de la relation (porte la cle etrangere reelle, voir
            // StationType::acces) : ce champ manquait ici, la relation n'etait geable que depuis
            // la fiche Station (voir TODO.md, audit CRUD/relations NN).
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
            'data_class' => Acces::class,
        ]);
    }
}
