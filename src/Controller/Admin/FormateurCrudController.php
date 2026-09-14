<?php

namespace App\Controller\Admin;

use App\Entity\Formateur;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FileField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;

class FormateurCrudController extends AbstractCrudController
{
      public function configureActions(Actions $actions): Actions
    {
        return $actions
        ->add(Crud:: PAGE_EDIT, Action::INDEX)
        ->add(Crud:: PAGE_INDEX, Action::DETAIL)
        ->add(Crud:: PAGE_EDIT, Action::DETAIL);

    }
    public function __construct(
        private UserPasswordHasherInterface $userPasswordHasher
    ) {
    }

   

    public static function getEntityFqcn(): string
    {
        return Formateur::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            // ID
            IdField::new('id')
                ->hideOnForm(),

           
            // COMPTE

            EmailField::new('email', 'Email'),

            TextField::new('password', 'Mot de passe')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type' => PasswordType::class,

                    'first_options' => [
                        'label' => 'Mot de passe',
                    ],

                    'second_options' => [
                        'label' => 'Confirmation du mot de passe',
                    ],
                ])
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->onlyOnForms(),

            // =========================
            // INFORMATIONS FORMATEUR
            // =========================

            TextField::new('nom', 'Nom'),

            TextField::new('prenom', 'Prénom'),

            TextField::new('experience', 'Expérience'),

            AssociationField::new('specialites', 'Spécialités'),
                     

            TextField::new('langue', 'Langue'),

            TextField::new('localisation', 'Localisation'),

            // =========================
            // IMAGES
            // =========================

            ImageField::new('image', 'Photos')
                ->setFormTypeOptions([
                    
                    'attr' => [
                        'accept' => 'image/*',
                    ],
                ])
                ->setBasePath('assets/images/utilisateurs')
                ->setUploadDir('public/assets/images/utilisateurs')
                ->setUploadedFileNamePattern(
                    '[randomhash].[extension]'
                ),

            // =========================
            // VIDEO
            // =========================

            FileField::new(
                'videoPresentation',
                'Vidéo de présentation'
            )
                ->setBasePath('assets/videos/formateurs')
                ->setUploadDir('public/assets/videos/formateurs')
                ->setUploadedFileNamePattern(
                    '[randomhash].[extension]'
                )
                ->setFormTypeOptions([
                    'attr' => [
                        'accept' => 'video/*',
                    ],
                ]),
        ];
    }

    /**
     * Création d'un Formateur
     * + création de son User.
     */
    public function persistEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        /** @var Formateur $formateur */
        $formateur = $entityInstance;

        // Le mot de passe est actuellement en clair
        $plainPassword = $formateur->getPassword();

        if (!$plainPassword) {
            throw new \RuntimeException(
                'Le mot de passe du formateur est obligatoire.'
            );
        }

        // Création du User
        $user = new User();

        // Email
        $user->setEmail(
            $formateur->getEmail()
        );

        // Nom
        $user->setNomUtilisateur(
            $formateur->getNom()
        );

        // Prénom
        $user->setPrenomUtilisateur(
            $formateur->getPrenom()
        );

        // Rôle
        $user->setRoles([
            'ROLE_FORMATEUR'
        ]);

        // Hash du mot de passe
        $hashedPassword = $this->userPasswordHasher->hashPassword(
            $user,
            $plainPassword
        );

        $user->setPassword($hashedPassword);

        // On ne garde PAS le mot de passe en clair
        $formateur->setPassword($hashedPassword);

        // Relation Formateur <-> User
        $formateur->setCompteUtilisateur($user);
        $user->setFormateur($formateur);

        // Persistance
        $entityManager->persist($formateur);
        $entityManager->persist($user);

        $entityManager->flush();
    }

    /**
     * Modification d'un Formateur
     * + synchronisation de son User.
     */
    public function updateEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        /** @var Formateur $formateur */
        $formateur = $entityInstance;

        // Récupération du User existant
        $user = $formateur->getCompteUtilisateur();

        // Si aucun User n'existe encore
        if ($user === null) {
            $user = new User();

            $formateur->setCompteUtilisateur($user);
            $user->setFormateur($formateur);

            $entityManager->persist($user);
        }

        // Synchronisation email
        $user->setEmail(
            $formateur->getEmail()
        );

        // Synchronisation nom
        $user->setNomUtilisateur(
            $formateur->getNom()
        );

        // Synchronisation prénom
        $user->setPrenomUtilisateur(
            $formateur->getPrenom()
        );

        // Rôle
        $user->setRoles([
            'ROLE_FORMATEUR'
        ]);

        // =========================
        // MOT DE PASSE
        // =========================

        $password = $formateur->getPassword();

        /*
         * Si le mot de passe envoyé est déjà un hash,
         * on ne le re-hashe pas.
         */
        if (
            $password !== null
            && $password !== ''
            && !str_starts_with($password, '$2y$')
            && !str_starts_with($password, '$argon2')
        ) {
            $hashedPassword = $this->userPasswordHasher->hashPassword(
                $user,
                $password
            );

            $user->setPassword($hashedPassword);
            $formateur->setPassword($hashedPassword);
        }

        $entityManager->persist($formateur);
        $entityManager->persist($user);

        $entityManager->flush();
    }
}