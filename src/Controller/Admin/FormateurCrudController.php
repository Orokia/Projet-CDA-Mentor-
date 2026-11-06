<?php

namespace App\Controller\Admin;

use App\Entity\Formateur;
use App\Security\EmailVerifier;
use App\Service\Admin\FormateurCrudService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FileField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Mime\Address;

class FormateurCrudController extends AbstractCrudController
{
    public function __construct(
        private FormateurCrudService $formateurCrudService,
        private EmailVerifier $emailVerifier,
        private LoggerInterface $logger
    ) {
    }

    /**
     
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL);
    }

    /**
     * Définir l'entité gérée par ce CRUD.
     */
    public static function getEntityFqcn(): string
    {
        return Formateur::class;
    }

    /**
     * Configurer les champs du formulaire.
     */
    public function configureFields(string $pageName): iterable
    {
        return [
            // Identifiant
            IdField::new('id')
                ->hideOnForm(),

            // Informations du compte
            EmailField::new('email', 'Email'),

            // Mot de passe et confirmation
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
                    'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                ])
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->onlyOnForms(),

            // Informations du formateur
            TextField::new('nom', 'Nom'),
            TextField::new('prenom', 'Prénom'),
            TextField::new('experience', 'Expérience'),
            AssociationField::new('specialites', 'Spécialités'),
            TextField::new('langue', 'Langue'),
            TextField::new('localisation', 'Localisation'),

            // Photo du formateur
            ImageField::new('image', 'Photo')
                ->setFormTypeOptions([
                    'attr' => [
                        'accept' => 'image/*',
                    ],
                ])
                ->setBasePath('assets/images/utilisateurs')
                ->setUploadDir('public/assets/images/utilisateurs')
                ->setUploadedFileNamePattern('[randomhash].[extension]'),

            // Vidéo de présentation
            FileField::new(
                'videoPresentation',
                'Vidéo de présentation'
            )
                ->setBasePath('assets/videos/formateurs')
                ->setUploadDir('public/assets/videos/formateurs')
                ->setUploadedFileNamePattern('[randomhash].[extension]')
                ->setFormTypeOptions([
                    'attr' => [
                        'accept' => 'video/*',
                    ],
                ]),
        ];
    }

    /**
     * Créer le formateur et son compte, puis envoyer l'e-mail.
     */
    public function persistEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (!$entityInstance instanceof Formateur) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        // Préparer le compte utilisateur via le service
        $user = $this->formateurCrudService->preparerCreation(
            $entityInstance
        );

        // Enregistrer le formateur et effectuer le flush
        parent::persistEntity($entityManager, $entityInstance);

        // Envoyer l'e-mail après l'enregistrement
        try {
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address(
                        'coulibaliorokia7@gmail.com',
                        'Mentor'
                    ))
                    ->to((string) $user->getEmail())
                    ->subject('Confirmez votre adresse e-mail - Mentor')
                    ->htmlTemplate(
                        'registration/confirmation_email.html.twig'
                    )
            );

            $this->addFlash(
                'success',
                'Le formateur a été créé. Un e-mail de confirmation a été envoyé.'
            );
        } catch (\Throwable $e) {
            // Enregistrer l'erreur dans les journaux
            $this->logger->error(
                'Échec de l’envoi de l’e-mail de confirmation au formateur.',
                ['exception' => $e]
            );

            $this->addFlash(
                'warning',
                'Le formateur a été créé, mais l’e-mail de confirmation n’a pas pu être envoyé.'
            );
        }
    }

    /**
     * Modifier le formateur et synchroniser son compte utilisateur.
     */
    public function updateEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (!$entityInstance instanceof Formateur) {
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        // Synchroniser le compte via le service
        $this->formateurCrudService->preparerModification(
            $entityInstance
        );

        // EasyAdmin effectue la mise à jour et le flush
        parent::updateEntity($entityManager, $entityInstance);
    }
}
