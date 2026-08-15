<?php

namespace App\Form;

use App\Entity\DocumentLigne;
use App\Entity\Ligne;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentLigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ligne', EntityType::class, [
                'class' => Ligne::class,
                'choice_label' => 'label',
            ])
            ->add('type')
            ->add('nom')
            ->add('url')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentLigne::class,
        ]);
    }
}
