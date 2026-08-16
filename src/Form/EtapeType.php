<?php

namespace App\Form;

use App\Entity\Etape;
use App\Entity\Tache;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtapeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('description', TextareaType::class, ['required' => false])
            ->add('tache', EntityType::class, [
                'class' => Tache::class,
                'choice_label' => 'nom',
            ])
            ->add('datetimeCreation', DateTimeType::class, ['widget' => 'single_text'])
            ->add('datetimeAchevement', DateTimeType::class, ['widget' => 'single_text', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Etape::class,
        ]);
    }
}
