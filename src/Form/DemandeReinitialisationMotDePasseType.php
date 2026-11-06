<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * « Mot de passe oublié » : saisie de l'adresse e-mail du compte.
 */
class DemandeReinitialisationMotDePasseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Adresse e-mail',
            'attr' => [
                'placeholder' => 'vous@exemple.fr',
                'autocomplete' => 'email',
            ],
            'constraints' => [
                new NotBlank(message: 'Veuillez saisir votre adresse e-mail.'),
                new Email(message: 'Veuillez saisir une adresse e-mail valide.'),
            ],
        ]);
    }
}
