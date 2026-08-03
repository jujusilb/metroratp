<?php

namespace App\Form;

use App\Entity\Correspondance;
use App\Entity\Desserte;
use App\Entity\Direction;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CorrespondanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choiceLabel = fn (Desserte $d): string => sprintf(
            '%s - %s',
            $d->getLigne()?->getLabel() ?? '?',
            $d->getStation()?->getLabel() ?? ('#' . $d->getId()),
        );
        $queryBuilder = fn (EntityRepository $er) => $er->createQueryBuilder('d')
            ->join('d.station', 's')->addSelect('s')
            ->join('d.ligne', 'l')->addSelect('l')
            ->orderBy('s.label', 'ASC')
            ->addOrderBy('l.label', 'ASC');

        $directionChoiceLabel = fn (Direction $d): string => sprintf(
            '%s - %s',
            $d->getLigne()?->getLabel() ?? '?',
            $d->getStation()?->getLabel() ?? ('#' . $d->getId()),
        );
        $directionQueryBuilder = fn (EntityRepository $er) => $er->createQueryBuilder('dir')
            ->join('dir.ligne', 'l')->addSelect('l')
            ->join('dir.desserteTerminus', 'dt')->addSelect('dt')
            ->join('dt.station', 's')->addSelect('s')
            ->orderBy('l.label', 'ASC')
            ->addOrderBy('s.label', 'ASC');

        $builder
            ->add('desserteA', EntityType::class, [
                'class' => Desserte::class,
                'label' => 'Première desserte',
                'choice_label' => $choiceLabel,
                'query_builder' => $queryBuilder,
            ])
            ->add('directionA', EntityType::class, [
                'class' => Direction::class,
                'label' => 'Direction sur la première ligne',
                'choice_label' => $directionChoiceLabel,
                'query_builder' => $directionQueryBuilder,
                'required' => false,
                'placeholder' => 'Toutes directions (correspondance générale)',
            ])
            ->add('desserteB', EntityType::class, [
                'class' => Desserte::class,
                'label' => 'Seconde desserte',
                'choice_label' => $choiceLabel,
                'query_builder' => $queryBuilder,
                'help' => "L'ordre des deux dessertes n'a pas d'importance, il est normalisé automatiquement.",
            ])
            ->add('directionB', EntityType::class, [
                'class' => Direction::class,
                'label' => 'Direction sur la seconde ligne',
                'choice_label' => $directionChoiceLabel,
                'query_builder' => $directionQueryBuilder,
                'required' => false,
                'placeholder' => 'Toutes directions (correspondance générale)',
            ])
            ->add('distance', IntegerType::class, [
                'required' => false,
                'help' => "En mètres. Laisser vide si la distance réelle n'est pas connue.",
            ])
            ->add('inZone', CheckboxType::class, [
                'label' => "En zone (pas besoin de repasser un contrôle d'accès)",
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Correspondance::class,
        ]);
    }
}
