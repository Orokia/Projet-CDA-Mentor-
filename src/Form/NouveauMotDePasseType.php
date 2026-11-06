<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Choix du nouveau mot de passe, saisi deux fois.
 */
class NouveauMotDePasseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'options' => [
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'help' => ContraintesMotDePasse::AIDE,
                'constraints' => ContraintesMotDePasse::liste(),
            ],
            'second_options' => [
                'label' => 'Confirmez le mot de passe',
            ],
        ]);
    }
}
