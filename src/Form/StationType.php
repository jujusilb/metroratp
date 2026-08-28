<?php

namespace App\Form;

use App\Entity\Acces;
use App\Entity\Plan;
use App\Entity\PoleEchange;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('plan', EntityType::class, [
                'class' => Plan::class,
                'choice_label' => 'secteur',
                'required' => false,
                'placeholder' => '-- Aucun --',
            ])
            ->add('poleEchange', EntityType::class, [
                'class' => PoleEchange::class,
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => '-- Aucun --',
            ])
            ->add('acces', EntityType::class, [
                'class' => Acces::class,
                'label' => 'Accès',
                'choice_label' => fn (Acces $a): string => $a->getLabel().($a->getNumero() ? ' (n°'.$a->getNumero().')' : ''),
                'multiple' => true,
                'required' => false,
                // Sans ca, Symfony muterait directement la collection getAcces() au lieu d'appeler
                // addAcce()/removeAcce() - qui seuls repercutent le changement cote proprietaire
                // (Acces::stations, qui porte la cle etrangere reelle).
                'by_reference' => false,
                'help' => "Choisir un accès l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Station::class,
        ]);
    }
}
