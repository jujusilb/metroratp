<?php

namespace App\Form;

use App\Entity\Desserte;
use App\Entity\Raison;
use App\Entity\Station;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RaisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            ->add('stations', EntityType::class, [
                'class' => Station::class,
                'choice_label' => 'label',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'help' => "Choisir une station l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
            ])
            // Une Desserte precise (Station x Ligne) peut etre inactive alors que sa Station
            // reste active par ailleurs (ex: un arret de bus toujours en service, mais un ancien
            // quai de metro jamais rouvert - "stations fantomes", voir TODO.md).
            ->add('dessertes', EntityType::class, [
                'class' => Desserte::class,
                'label' => 'Dessertes',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'help' => "Choisir une desserte l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
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
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Raison::class,
        ]);
    }
}
