<?php

namespace App\Form;

use App\Entity\Materiel;
use App\Entity\TypeMateriel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaterielType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('anneeProduction')
            ->add('constructeur', null, ['required' => false])
            ->add('vitesseMaxKmh', null, ['required' => false])
            ->add('typeMateriel', EntityType::class, [
                'class' => TypeMateriel::class,
                'choice_label' => 'id',
            ])
            // Remplace l'ancienne page CRUD dediee MaterielLigne : editee directement ici, chaque
            // ligne portant ses propres dates (voir TODO.md, simplification des tables de jointure).
            ->add('materielLignes', CollectionType::class, [
                'entry_type' => MaterielLigneEmbeddedType::class,
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
            'data_class' => Materiel::class,
        ]);
    }
}
