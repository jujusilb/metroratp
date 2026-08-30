<?php

namespace App\Form;

use App\Entity\Acces;
use App\Entity\Plan;
use App\Entity\PointInteret;
use App\Entity\PoleEchange;
use App\Entity\Station;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('plan', EntityType::class, [
                'class' => Plan::class,
                'choice_label' => 'secteur',
                'required' => false,
                'placeholder' => '-- Aucun --',
            ])
            ->add('poleEchange', EntityType::class, [
                'class' => PoleEchange::class,
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => '-- Aucun --',
            ])
            ->add('acces', EntityType::class, [
                'class' => Acces::class,
                'label' => 'Accès',
                'choice_label' => fn (Acces $a): string => $a->getLabel().($a->getNumero() ? ' (n°'.$a->getNumero().')' : ''),
                'multiple' => true,
                'required' => false,
                // Sans ca, Symfony muterait directement la collection getAcces() au lieu d'appeler
                // addAcce()/removeAcce() - qui seuls repercutent le changement cote proprietaire
                // (Acces::stations, qui porte la cle etrangere reelle).
                'by_reference' => false,
                'help' => "Choisir un accès l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
            ])
            ->add('pointsInteret', EntityType::class, [
                'class' => PointInteret::class,
                'label' => 'Lieux à proximité',
                'choice_label' => 'label',
                'multiple' => true,
                'required' => false,
                // "pointsInteret" n'a pas de "s" final (le pluriel est au milieu du mot compose) :
                // Symfony ne peut pas deviner tout seul addPointInteret()/removePointInteret() a
                // partir du nom de propriete (contrairement a "acces" -> "addAcce"/"removeAcce"
                // juste au-dessus, ou la deduction naive fonctionne). getter/setter explicites,
                // qui passent par ces methodes pour repercuter le changement cote proprietaire
                // (PointInteret::stations, qui porte la cle etrangere reelle) plutot que de muter
                // directement la collection.
                'getter' => static fn (Station $station): iterable => $station->getPointsInteret(),
                'setter' => static function (Station $station, iterable $pointsInteret): void {
                    $nouveaux = $pointsInteret instanceof \Traversable ? iterator_to_array($pointsInteret) : $pointsInteret;
                    foreach ($station->getPointsInteret()->toArray() as $existant) {
                        if (!\in_array($existant, $nouveaux, true)) {
                            $station->removePointInteret($existant);
                        }
                    }
                    foreach ($nouveaux as $pointInteret) {
                        $station->addPointInteret($pointInteret);
                    }
                },
                'help' => "Choisir un lieu l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Station::class,
        ]);
    }
}
