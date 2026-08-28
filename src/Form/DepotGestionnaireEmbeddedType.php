<?php

namespace App\Form;

use App\Entity\DepotGestionnaire;
use App\Entity\Gestionnaire;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Version "imbriquee" de DepotGestionnaireType, sans le champ depot (implicite : on est deja sur
 * la fiche du Depot concerne) - utilisee dans le CollectionType de DepotType, pas de page CRUD
 * separee pour cette relation (voir TODO.md, simplification des tables de jointure datees).
 */
class DepotGestionnaireEmbeddedType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('gestionnaire', EntityType::class, [
                'class' => Gestionnaire::class,
                'choice_label' => 'label',
            ])
            ->add('arrivee', null, ['required' => false])
            ->add('fin', null, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DepotGestionnaire::class,
        ]);
    }
}
