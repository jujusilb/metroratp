<?php

namespace App\Form;

use App\Entity\Desserte;
use App\Entity\StyleStation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StyleStationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label')
            // CollectionType d'EntiteParIdentifiantType (pas un <select> EntityType) : Desserte a
            // ~33600 lignes, bien trop pour un multi-select classique (fait planter le formulaire
            // par epuisement memoire - bug reel trouve en verifiant RaisonType, voir TODO.md audit
            // CRUD). Saisie par id brut ligne par ligne, meme widget d'ajout/suppression que les
            // relations datees (Depot/Materiel, voir assets/js/collection-widget.js).
            ->add('dessertes', CollectionType::class, [
                'entry_type' => EntiteParIdentifiantType::class,
                'entry_options' => [
                    'class' => Desserte::class,
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                // Sans ca, Symfony mute directement la collection getDessertes() au lieu
                // d'appeler addDesserte()/removeDesserte() : le cote proprietaire de la relation
                // (Desserte::styleStation, qui porte la cle etrangere) ne serait alors jamais mis
                // a jour et rien ne serait persiste.
                'by_reference' => false,
                'label' => false,
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
