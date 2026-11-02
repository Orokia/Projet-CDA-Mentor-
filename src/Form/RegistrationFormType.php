<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder

            /*
             * NOM
             */
            ->add('nomUtilisateur', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Entrez votre nom',
                    'class' => 'form-control',
                    'autocomplete' => 'family-name',
                ],
                'row_attr' => [
                    'class' => 'form-group mb-3',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre nom.'
                    ),
                    new Length(
                        min: 2,
                        max: 255,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])

            /*
             * PRÉNOM
             */
            ->add('prenomUtilisateur', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Entrez votre prénom',
                    'class' => 'form-control',
                    'autocomplete' => 'given-name',
                ],
                'row_attr' => [
                    'class' => 'form-group mb-3',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre prénom.'
                    ),
                    new Length(
                        min: 2,
                        max: 255,
                        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])

            /*
             * EMAIL
             */
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => [
                    'placeholder' => 'exemple@email.com',
                    'class' => 'form-control',
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre adresse e-mail.'
                    ),
                    new Email(
                        message: 'Veuillez saisir une adresse e-mail valide.'
                    ),
                ],
            ])

            /*
             * MOT DE PASSE
             */
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Votre mot de passe',
                    'class' => 'form-control',
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
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
                ],
            ])

            /*
             * CONDITIONS
             */
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J’accepte les conditions générales d’utilisation.',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(
                        message: 'Vous devez accepter les conditions générales.'
                    ),
                ],
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}