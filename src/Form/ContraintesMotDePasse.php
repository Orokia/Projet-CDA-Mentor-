<?php

namespace App\Form;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Règles d'un mot de passe Mentor, communes à l'inscription et à la réinitialisation.
 */
final class ContraintesMotDePasse
{
    public const AIDE = 'Au moins 8 caractères, avec une majuscule, une minuscule, un chiffre et un caractère spécial.';

    /**
     * @return list<Constraint>
     */
    public static function liste(): array
    {
        return [
            new NotBlank(
                message: 'Veuillez saisir un mot de passe.'
            ),

            new Length(
                min: 8,
                max: 4096,
                minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'
            ),

            new Regex(
                pattern: '/[A-Z]/',
                message: 'Le mot de passe doit contenir au moins une lettre majuscule.'
            ),

            new Regex(
                pattern: '/[a-z]/',
                message: 'Le mot de passe doit contenir au moins une lettre minuscule.'
            ),

            new Regex(
                pattern: '/[0-9]/',
                message: 'Le mot de passe doit contenir au moins un chiffre.'
            ),

            new Regex(
                pattern: '/[^a-zA-Z0-9]/',
                message: 'Le mot de passe doit contenir au moins un caractère spécial.'
            ),
        ];
    }
}
