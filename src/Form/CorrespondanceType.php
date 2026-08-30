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
            // EntiteParIdentifiantType (pas EntityType) : Desserte a ~33600 lignes, bien trop
            // pour un <select> classique (fait planter le formulaire par epuisement memoire,
            // voir EntiteParIdentifiantType/TODO.md). Saisie par id brut, le gabarit affiche le
            // libelle lisible de la valeur actuelle a cote.
            ->add('desserteA', EntiteParIdentifiantType::class, [
                'class' => Desserte::class,
                'label' => 'Première desserte, par id',
                'help' => "Id de Desserte - voir la fiche d'une Station pour le retrouver.",
            ])
            ->add('directionA', EntityType::class, [
                'class' => Direction::class,
                'label' => 'Direction sur la première ligne',
                'choice_label' => $directionChoiceLabel,
                'query_builder' => $directionQueryBuilder,
                'required' => false,
                'placeholder' => 'Toutes directions (correspondance générale)',
            ])
            ->add('desserteB', EntiteParIdentifiantType::class, [
                'class' => Desserte::class,
                'label' => 'Seconde desserte, par id',
                'help' => "Id de Desserte - l'ordre des deux dessertes n'a pas d'importance, il est normalisé automatiquement.",
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
