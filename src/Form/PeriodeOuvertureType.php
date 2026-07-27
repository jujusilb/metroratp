<?php

namespace App\Form;

use App\Entity\Desserte;
use App\Entity\PeriodeOuverture;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PeriodeOuvertureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('desserte', EntityType::class, [
                'class' => Desserte::class,
                'choice_label' => fn (Desserte $d): string => sprintf(
                    '%s (%s)',
                    $d->getStation()?->getLabel() ?? '#' . $d->getId(),
                    $d->getLigne()?->getLabel() ?? '?',
                ),
            ])
            ->add('ordre', null, [
                'help' => "Classe les periodes d'une meme desserte dans l'ordre chronologique (1 = la premiere).",
            ])
            ->add('ouverture', null, [
                'required' => false,
                'help' => 'Laisser vide si la date exacte est inconnue.',
            ])
            ->add('fermeture', null, [
                'required' => false,
                'help' => 'Laisser vide si la station est toujours ouverte depuis cette periode.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PeriodeOuverture::class,
        ]);
    }
}
