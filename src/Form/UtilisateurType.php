<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', null, [
                'label' => "Nom d'utilisateur",
            ])
            ->add('email')
            ->add('prenom', null, ['required' => false])
            ->add('nom', null, ['required' => false])
            ->add('telephone', null, ['required' => false])
            ->add('adresseLigne1', null, [
                'required' => false,
                'label' => 'Adresse (ligne 1)',
            ])
            ->add('adresseLigne2', null, [
                'required' => false,
                'label' => 'Adresse (ligne 2)',
            ])
            ->add('codePostal', null, ['required' => false])
            ->add('ville', null, ['required' => false])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => $options['password_requis'],
                'label' => 'Mot de passe',
                'help' => $options['password_requis']
                    ? null
                    : 'Laisser vide pour conserver le mot de passe actuel.',
                'constraints' => $options['password_requis']
                    ? [new NotBlank(), new Length(min: 8, max: 4096)]
                    : [new Length(min: 8, max: 4096)],
            ])
        ;

        if ($options['montrer_admin']) {
            $builder->add('estAdmin', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'data' => $options['admin_par_defaut'],
                'label' => 'Administrateur (accès à la gestion des utilisateurs)',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
            'password_requis' => true,
            'admin_par_defaut' => false,
            'montrer_admin' => true,
        ]);
    }
}
