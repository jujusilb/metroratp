<?php

namespace App\Form;

use App\Entity\Acces;
use App\Entity\StyleAcces;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StyleAccesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('acces', EntityType::class, [
                'class' => Acces::class,
                'label' => 'Accès',
                'multiple' => true,
                'required' => false,
                // Sans ca, Symfony mute directement la collection getAcces() au lieu d'appeler
                // addAcces()/removeAcces() : le cote proprietaire de la relation (Acces::styleAcces,
                // qui porte la cle etrangere) ne serait alors jamais mis a jour.
                'by_reference' => false,
                // Station (via la premiere Station rattachee) + label/numero de l'Acces, pour
                // distinguer les accès homonymes de stations différentes (meme logique que
                // StyleStationType).
                'choice_label' => function (Acces $a): string {
                    $station = $a->getStations()->first();

                    return sprintf(
                        '%s - %s%s',
                        $station ? $station->getLabel() : '?',
                        $a->getLabel(),
                        $a->getNumero() ? ' (n°'.$a->getNumero().')' : '',
                    );
                },
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('a')
                    ->leftJoin('a.stations', 'station')->addSelect('station')
                    ->orderBy('station.label', 'ASC')
                    ->addOrderBy('a.label', 'ASC'),
                'help' => "Choisir un accès l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StyleAcces::class,
        ]);
    }
}
