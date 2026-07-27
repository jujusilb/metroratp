<?php

namespace App\Form;

use App\Entity\Desserte;
use App\Entity\StyleStation;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StyleStationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('dessertes', EntityType::class, [
                'class' => Desserte::class,
                'label' => 'Stations',
                'multiple' => true,
                'required' => false,
                // Sans ca, Symfony mute directement la collection getDessertes() au lieu
                // d'appeler addDesserte()/removeDesserte() : le cote proprietaire de la relation
                // (Desserte::styleStation, qui porte la cle etrangere) ne serait alors jamais mis
                // a jour et rien ne serait persiste.
                'by_reference' => false,
                // On precise la ligne devant le nom de la station : plusieurs stations
                // (Chatelet, Nation, Republique...) desservent plusieurs lignes et seraient
                // sinon indiscernables dans la liste.
                'choice_label' => fn (Desserte $d): string => sprintf(
                    '%s - %s',
                    $d->getLigne()?->getLabel() ?? '?',
                    $d->getStation()?->getLabel() ?? ('#' . $d->getId()),
                ),
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('d')
                    ->join('d.station', 's')->addSelect('s')
                    ->join('d.ligne', 'l')->addSelect('l')
                    ->orderBy('s.label', 'ASC')
                    ->addOrderBy('l.label', 'ASC'),
                'help' => "Choisir une station l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StyleStation::class,
        ]);
    }
}
