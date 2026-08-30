<?php

namespace App\Form;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Champ "id brut" pour choisir une entite parmi un trop grand nombre de candidats pour un
 * <select> classique (charger des dizaines de milliers de lignes en options fait planter le
 * formulaire par epuisement memoire - bug reel trouve sur RaisonType/StyleStationType/
 * StyleEcritureType, voir TODO.md "Audit complet CRUD"). Aucune bibliotheque d'autocomplete
 * (ux-autocomplete, select2...) n'est installee dans le projet : ce simple champ "id + label
 * affiche a cote" est le compromis le plus simple qui evite d'ajouter une nouvelle dependance.
 *
 * L'id soumis est resolu en entite via EntityManager::find() (TransformationFailedException,
 * donc erreur de formulaire propre, si l'id n'existe pas). Le gabarit du formulaire appelant
 * doit afficher un lien/libelle lisible de la valeur actuelle a cote du champ (voir
 * mission/_form.html.twig, correspondance/_form.html.twig) puisque l'id seul ne dit rien a
 * l'utilisateur.
 */
class EntiteParIdentifiantType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entityManager = $this->entityManager;
        $classe = $options['class'];

        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?object $entite): ?int => $entite?->getId(),
            static function (?int $id) use ($entityManager, $classe): ?object {
                if (null === $id) {
                    return null;
                }
                $entite = $entityManager->find($classe, $id);
                if (null === $entite) {
                    throw new TransformationFailedException(sprintf("Aucune entite '%s' avec l'id %d.", $classe, $id));
                }

                return $entite;
            },
        ));
    }

    public function getParent(): string
    {
        return IntegerType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('class');
    }
}
