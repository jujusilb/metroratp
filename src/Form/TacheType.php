<?php

namespace App\Form;

use App\Entity\StatutTache;
use App\Entity\Tache;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TacheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('description', TextareaType::class, ['required' => false])
            ->add('statut', EntityType::class, [
                'class' => StatutTache::class,
                'choice_label' => 'label',
            ])
            ->add('datetimeCreation', DateTimeType::class, ['widget' => 'single_text'])
            ->add('datetimeAction', DateTimeType::class, ['widget' => 'single_text', 'required' => false])
            ->add('datetimeAchevement', DateTimeType::class, ['widget' => 'single_text', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tache::class,
        ]);
    }
}
