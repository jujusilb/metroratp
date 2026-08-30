<?php

namespace App\Form;

use App\Entity\Desserte;
use App\Entity\Ligne;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Le Troncon n'est volontairement pas un champ de ce formulaire : il est fixe par le contexte
 * (bouton "Ajouter une desserte" sur la fiche Troncon, voir TronconDesserteController::new()).
 * Desserte, elle, est restreinte a la Ligne du Troncon (remarque utilisateur, cf. TODO.md audit
 * CRUD/relations NN) - deduite de la 1ere TronconDesserte deja existante sur ce Troncon (un
 * Troncon n'a pas de champ "ligne" direct). Pour un Troncon flambant neuf (aucune TronconDesserte
 * encore), aucune Ligne n'est deductible : repli sur EntiteParIdentifiantType (id brut) plutot que
 * d'afficher un <select> de ~33600 Desserte (meme risque de plantage memoire que RaisonType/
 * MissionType, voir TODO.md).
 */
class TronconDesserteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ?Ligne $ligne */
        $ligne = $options['ligne'];

        if (null !== $ligne) {
            $builder->add('desserte', EntityType::class, [
                'class' => Desserte::class,
                'choice_label' => fn (Desserte $d): string => $d->getStation()?->getLabel() ?? ('#' . $d->getId()),
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('d')
                    ->join('d.station', 's')->addSelect('s')
                    ->andWhere('d.ligne = :ligne')
                    ->setParameter('ligne', $ligne)
                    ->orderBy('s.label', 'ASC'),
            ]);
        } else {
            $builder->add('desserte', EntiteParIdentifiantType::class, [
                'class' => Desserte::class,
                'label' => 'Desserte, par id',
                'help' => "Aucune ligne connue pour ce tronçon (première desserte) : saisir l'id directement (voir la fiche d'une Station).",
            ]);
        }

        $builder
            ->add('typeDesserte', EntityType::class, [
                'class' => TypeDesserte::class,
                'choice_label' => 'label',
                'label' => 'Type (rôle)',
            ])
            ->add('dureeReelleSecondes', null, [
                'required' => false,
                'label' => 'Durée réelle (secondes)',
                'help' => 'Uniquement significatif côté "Départ".',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TronconDesserte::class,
            'ligne' => null,
        ]);
        $resolver->setAllowedTypes('ligne', ['null', Ligne::class]);
    }
}
