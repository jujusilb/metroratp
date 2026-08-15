<?php

namespace App\Form;

use App\Entity\Sanitaire;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SanitaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ligneLabel', null, ['required' => false])
            ->add('label')
            ->add('accessiblePublic', null, ['required' => false])
            ->add('tarif', null, ['required' => false])
            ->add('accesPassNavigoTicketT', null, ['required' => false])
            ->add('accesBoutonPoussoir', null, ['required' => false])
            ->add('enZoneControlee', null, ['required' => false])
            ->add('horsZoneControleeStation', null, ['required' => false])
            ->add('horsZoneControleeVoiePublique', null, ['required' => false])
            ->add('accessibilitePmr', null, ['required' => false])
            ->add('localisation', null, ['required' => false])
            ->add('latitude')
            ->add('longitude')
            ->add('gestionnaire', null, ['required' => false])
            ->add('station', EntityType::class, [
                'class' => Station::class,
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => '-- Aucune --',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sanitaire::class,
        ]);
    }
}
