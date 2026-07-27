<?php

namespace App\Form;

use App\Entity\Desserte;
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
            ->add('tronconDesserte', EntityType::class, [
                'class' => TronconDesserte::class,
                'label' => 'Troncon (depart)',
                'choice_label' => fn (TronconDesserte $td): string => sprintf(
                    'Troncon #%d - %s (%s)',
                    $td->getTroncon()?->getId() ?? 0,
                    $td->getDesserte()?->getStation()?->getLabel() ?? '?',
                    $td->getTypeDesserte()?->getLabel() ?? '?',
                ),
            ])
            ->add('direction', EntityType::class, [
                'class' => Desserte::class,
                'choice_label' => fn (Desserte $d): string => $d->getStation()?->getLabel() ?? ('#' . $d->getId()),
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
