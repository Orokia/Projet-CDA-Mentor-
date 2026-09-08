<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\Query\Expr\Func;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        public UserPasswordHasherInterface $userpasswordhasher
    ){}

    

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
        ->add(Crud:: PAGE_EDIT, Action::INDEX)
        ->add(Crud:: PAGE_INDEX, Action::DETAIL)
        ->add(Crud:: PAGE_EDIT, Action::DETAIL);

    }

    
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
           
            TextField::new('nomUtilisateur'),
            TextField::new('prenomUtilisateur'),
            EmailField::new('email'),
            ImageField::new('photoUtilisateur')
            ->setFormTypeOptions([
               
                'attr'=>[
                    'accept'=>'image/*'
                ]
            ])
            ->setBasePath("assets/images/utilisateurs")
            ->setUploadDir("public/assets/images/utilisateurs")
            ->setUploadedFileNamePattern('[randomhash].[extension]'),
            TextField::new('password')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type'=> PasswordType::class,
                'first_options'=> [
                    'label'=>'Password',
                    'row_attr'=>[
                        'class'=>"col-md-6 col-xxl-5"
                    ],
                    ],
                'second_options'=>[
                    'label'=> 'Confirm Password',
                    'row_attr'=>[
                        'class'=>"col-md-6 col-xxl-5"
                    ],
                    

                    ],
                'mapped'=> false,

            ])
            ->setRequired($pageName === Crud::PAGE_NEW)

            ->onlyOnForms(),
            
        ];
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface{
        $formBuilder = parent::createNewFormBuilder($entityDto, $formOptions, $context);
        return $this->addPasswordEventListener($formBuilder);

    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface{
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        return $this->addPasswordEventListener($formBuilder);

    }
    public function addPasswordEventListener( FormBuilderInterface $formBuilder ){
        return $formBuilder->addEventListener(FormEvents::POST_SUBMIT, $this->hashPassword());
    }
    
    public function hashPassword()
{
    return function ($event) {
        $form = $event->getForm();

        if (!$form->isValid()) {
            return;
        }

        $user = $form->getData(); // ✅ récupérer l'entité User

        if (!$user) {
            return;
        }

        $password = $form->get('password')->getData();

        if (!$password) {
            return;
        }

        $hash = $this->userpasswordhasher->hashPassword($user, $password);
        $user->setPassword($hash);
    };
}
}
