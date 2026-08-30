<?php

namespace App\Form;

use App\Entity\Direction;
use App\Entity\Mission;
use App\Entity\Service;
use App\Entity\TronconDesserte;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numero')
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'id',
            ])
            // EntiteParIdentifiantType (pas EntityType) : TronconDesserte a ~129000 lignes, bien
            // trop pour un <select> classique (fait planter le formulaire par epuisement
            // memoire, voir EntiteParIdentifiantType/TODO.md). Saisie par id brut, le gabarit
            // affiche le libelle lisible de la valeur actuelle a cote.
            ->add('tronconDesserte', EntiteParIdentifiantType::class, [
                'class' => TronconDesserte::class,
                'label' => 'Troncon (depart), par id',
                'help' => "Id de TronconDesserte - voir la fiche d'un Troncon ou d'une Desserte pour le retrouver.",
            ])
            ->add('direction', EntityType::class, [
                'class' => Direction::class,
                'choice_label' => fn (Direction $d): string => sprintf(
                    '%s - %s',
                    $d->getLigne()?->getLabel() ?? '?',
                    $d->getStation()?->getLabel() ?? ('#' . $d->getId()),
                ),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mission::class,
        ]);
    }
}
