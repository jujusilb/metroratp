<?php

namespace App\Form;

use App\Entity\Gestionnaire;
use App\Entity\Ligne;
use App\Entity\TypeTransport;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('couleur')
            ->add('typeTransport', EntityType::class, [
                'class' => TypeTransport::class,
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => '-- Choisir un type de transport --',
                'label' => 'Type de transport',
            ])
            ->add('gestionnaire', EntityType::class, [
                'class' => Gestionnaire::class,
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => '-- Choisir un gestionnaire --',
                'label' => 'Gestionnaire',
            ])
            // Utilise par le calculateur de trajet pour ne pas proposer une ligne fermee au
            // moment demande (ex: Noctilien en pleine journee) - voir TrajetFinder::estEnService.
            ->add('horaireLignes', CollectionType::class, [
                'entry_type' => HoraireLigneEmbeddedType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ligne::class,
        ]);
    }
}
