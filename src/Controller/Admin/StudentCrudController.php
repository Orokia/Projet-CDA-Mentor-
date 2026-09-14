<?php

namespace App\Controller\Admin;

use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class StudentCrudController extends AbstractCrudController
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
        return Student::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [


            IdField::new('id')
                ->hideOnForm(),

            // COMPTE UTILISATEUR

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

                    'mapped' => false,
                ])
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->onlyOnForms(),

            // =========================
            // INFORMATIONS ÉTUDIANT
            // =========================

            TextField::new('nom', 'Nom'),

            TextField::new('prenom', 'Prénom'),

            // =========================
            // PHOTO
            // =========================

            ImageField::new('photo', 'Photo')
                ->setBasePath('assets/images/utilisateurs')
                ->setUploadDir('public/assets/images/utilisateurs')
                ->setUploadedFileNamePattern(
                    '[randomhash].[extension]'
                )
                ->setFormTypeOptions([
                    'multiple' => false,
                    'attr' => [
                        'accept' => 'image/*',
                    ],
                ]),
        ];
    }

    /**
     * Récupère l'email du User
     * lors de la modification d'un étudiant.
     */
    public function createEditFormBuilder(
        EntityDto $entityDto,
        KeyValueStore $formOptions,
        AdminContext $context
    ): FormBuilderInterface {

        $formBuilder = parent::createEditFormBuilder(
            $entityDto,
            $formOptions,
            $context
        );

        $formBuilder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function ($event) {

                $form = $event->getForm();

                /** @var Student|null $student */
                $student = $event->getData();

                if (!$student) {
                    return;
                }

                $user = $student->getUser();

                if ($user !== null) {
                    $form->get('email')->setData(
                        $user->getEmail()
                    );
                }
            }
        );

        return $formBuilder;
    }

    /**
     * Création d'un Student + création automatique du User.
     */
    public function persistEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {

        /** @var Student $student */
        $student = $entityInstance;

        // Récupération du formulaire EasyAdmin
        $context = $this->getContext();

        if (!$context) {
            throw new \RuntimeException(
                'Impossible de récupérer le contexte EasyAdmin.'
            );
        }

        $request = $context->getRequest();

        // =========================
        // RÉCUPÉRATION DES DONNÉES
        // =========================

        $email = $request->request->all('Student')['email'] ?? null;
        $password = $request->request->all('Student')['password'] ?? null;

        // Avec RepeatedType, password peut être un tableau
        if (is_array($password)) {
            $password = $password['first'] ?? null;
        }

        // =========================
        // CRÉATION DU USER
        // =========================

        $user = new User();

        if ($email !== null && $email !== '') {
            $user->setEmail($email);
        }

        $user->setNomUtilisateur(
            $student->getNom()
        );

        $user->setPrenomUtilisateur(
            $student->getPrenom()
        );

        $user->setRoles([
            'ROLE_STUDENT'
        ]);

        // =========================
        // PASSWORD
        // =========================

        if ($password !== null && $password !== '') {

            $hashedPassword = $this->userPasswordHasher->hashPassword(
                $user,
                $password
            );

            $user->setPassword($hashedPassword);
        }

        // =========================
        // RELATION STUDENT <-> USER
        // =========================

        $user->setStudent($student);
        $student->setUser($user);

        // =========================
        // SAUVEGARDE
        // =========================

        $entityManager->persist($student);
        $entityManager->persist($user);

        $entityManager->flush();
    }

    /**
     * Modification d'un Student + modification de son User.
     */
    public function updateEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {

        /** @var Student $student */
        $student = $entityInstance;

        $context = $this->getContext();

        if (!$context) {
            throw new \RuntimeException(
                'Impossible de récupérer le contexte EasyAdmin.'
            );
        }

        $request = $context->getRequest();

        // =========================
        // RÉCUPÉRATION DU USER
        // =========================

        $user = $student->getUser();

        // Si l'étudiant n'a pas encore de compte
        if ($user === null) {

            $user = new User();

            $user->setStudent($student);
            $student->setUser($user);

            $entityManager->persist($user);
        }

        // =========================
        // RÉCUPÉRATION DES DONNÉES
        // =========================

        $studentData = $request->request->all('Student');

        $email = $studentData['email'] ?? null;
        $password = $studentData['password'] ?? null;

        // RepeatedType
        if (is_array($password)) {
            $password = $password['first'] ?? null;
        }

        // =========================
        // EMAIL
        // =========================

        if ($email !== null && $email !== '') {
            $user->setEmail($email);
        }

        // =========================
        // NOM / PRÉNOM
        // =========================

        $user->setNomUtilisateur(
            $student->getNom()
        );

        $user->setPrenomUtilisateur(
            $student->getPrenom()
        );

        // =========================
        // RÔLE
        // =========================

        $user->setRoles([
            'ROLE_STUDENT'
        ]);

        // =========================
        // PASSWORD
        // =========================

        if ($password !== null && $password !== '') {

            $hashedPassword = $this->userPasswordHasher->hashPassword(
                $user,
                $password
            );

            $user->setPassword($hashedPassword);
        }

        // =========================
        // SAUVEGARDE
        // =========================

        $entityManager->persist($student);
        $entityManager->persist($user);

        $entityManager->flush();
    }
}