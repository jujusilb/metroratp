<?php

namespace App\Form;

use App\Entity\HoraireLigne;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Version "imbriquee" utilisee dans le CollectionType de LigneType, pas de page CRUD separee
 * (voir TODO.md, meme principe que MaterielLigneEmbeddedType).
 */
class HoraireLigneEmbeddedType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeJour', ChoiceType::class, [
                'choices' => array_combine(HoraireLigne::TYPES_JOUR, HoraireLigne::TYPES_JOUR),
                'label' => 'Type de jour',
            ])
            ->add('premierDepart', TimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Premier départ',
            ])
            ->add('dernierDepart', TimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Dernier départ',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HoraireLigne::class,
        ]);
    }
}
