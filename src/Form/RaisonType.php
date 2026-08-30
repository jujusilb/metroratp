<?php

namespace App\Form;

use App\Entity\Desserte;
use App\Entity\Raison;
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
            // L'inactivite se marque au niveau de la Desserte (Station x Ligne), pas de la
            // Station : une Station peut rester active (ex: un arret de bus toujours en service)
            // alors qu'une Desserte precise est definitivement morte (ancien quai de metro jamais
            // rouvert) - voir TODO.md "stations fantomes". Une Station sans AUCUNE Desserte reelle
            // (aucun service jamais imagine) utilise une Desserte a Ligne nulle comme simple
            // support de la Raison (LEFT JOIN ci-dessous : sinon ces dessertes-la, precisement
            // celles qui nous interessent le plus ici, disparaitraient de la liste).
            ->add('dessertes', EntityType::class, [
                'class' => Desserte::class,
                'label' => 'Dessertes',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'help' => "Choisir une desserte l'ajoute à la liste ; cliquer sur « Retirer » l'enlève.",
                'choice_label' => fn (Desserte $d): string => sprintf(
                    '%s - %s',
                    $d->getLigne()?->getLabel() ?? '(aucune ligne)',
                    $d->getStation()?->getLabel() ?? ('#' . $d->getId()),
                ),
                // Limite aux dessertes DEJA taguees (par n'importe quelle Raison) : le reseau
                // complet fait ~31 800 Desserte, bien trop pour un simple <select multiple> (fait
                // planter le formulaire par epuisement memoire, verifie en navigateur). Une
                // nouvelle desserte a taguer se peuple via une commande dediee (voir
                // app:creer-stations-fantomes/app:migrer-raison-station-vers-desserte), pas en
                // parcourant tout le reseau ici - ce champ sert a re-affecter/retirer une raison
                // parmi les cas deja identifies, pas a en decouvrir de nouveaux.
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('d')
                    ->join('d.station', 's')->addSelect('s')
                    ->leftJoin('d.ligne', 'l')->addSelect('l')
                    ->where('d.raisons IS NOT EMPTY')
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
