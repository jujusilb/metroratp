<?php

namespace App\Form;

use App\Entity\Depot;
use App\Entity\Ville;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
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
            // Les 3 collections suivantes remplacent les anciennes pages CRUD dediees
            // (DepotGestionnaire/DepotLigne/MaterielDepot) : editees directement ici, chaque ligne
            // portant ses propres dates - un simple multi-select ne pourrait pas porter ces dates
            // (voir TODO.md, simplification des tables de jointure).
            ->add('depotGestionnaires', CollectionType::class, [
                'entry_type' => DepotGestionnaireEmbeddedType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ])
            ->add('depotLignes', CollectionType::class, [
                'entry_type' => DepotLigneEmbeddedType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ])
            ->add('materielDepots', CollectionType::class, [
                'entry_type' => MaterielDepotEmbeddedType::class,
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
            'data_class' => Depot::class,
        ]);
    }
}
