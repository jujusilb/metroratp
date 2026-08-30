<?php

namespace App\Form;

use App\Entity\Desserte;
use App\Entity\Direction;
use App\Entity\Ligne;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * La Ligne n'est volontairement pas un champ de ce formulaire : elle est fixee par le contexte
 * (bouton "Ajouter une direction" sur la fiche Ligne, voir DirectionController::new()) et posee
 * directement sur l'entite avant creation du formulaire. Ca permet de restreindre le champ
 * desserteTerminus aux seules Desserte de CETTE ligne (remarque utilisateur, cf. TODO.md audit
 * CRUD/relations NN) sans avoir a gerer un <select> Ligne + JS de rafraichissement.
 */
class DirectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Ligne $ligne */
        $ligne = $options['ligne'];

        $builder
            ->add('desserteTerminus', EntityType::class, [
                'class' => Desserte::class,
                'label' => 'Terminus (desserte)',
                'choice_label' => fn (Desserte $d): string => $d->getStation()?->getLabel() ?? ('#' . $d->getId()),
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('d')
                    ->join('d.station', 's')->addSelect('s')
                    ->andWhere('d.ligne = :ligne')
                    ->setParameter('ligne', $ligne)
                    ->orderBy('s.label', 'ASC'),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Direction::class,
        ]);
        $resolver->setRequired('ligne');
        $resolver->setAllowedTypes('ligne', Ligne::class);
    }
}
