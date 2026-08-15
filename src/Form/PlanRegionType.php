<?php

namespace App\Form;

use App\Entity\PlanRegion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanRegionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numero')
            ->add('ordre')
            ->add('label')
            ->add('urlPdf')
            ->add('urlFiche', null, [
                'required' => false,
            ])
            ->add('tailleFichierMo', null, [
                'required' => false,
            ])
            ->add('datePublication', null, [
                'required' => false,
            ])
            ->add('format', null, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanRegion::class,
        ]);
    }
}
