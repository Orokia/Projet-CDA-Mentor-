<?php

namespace App\Controller\Admin;

use App\Entity\Formation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class FormationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Formation::class;
    }

    
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('titre'),
            TextEditorField::new('description'),
            ImageField::new('image')
            ->setFormTypeOptions([
               
                'attr'=>[
                    'accept'=>'image/*'
                ]
            ])
            ->setBasePath("assets/images/products")
            ->setUploadDir("public/assets/images/products")
            ->setUploadedFileNamePattern('[randomhash].[extension]'),
            MoneyField::new('prix')->setCurrency("EUR"),
             IntegerField::new('duree', 'Durée')
    ->setRequired(true),
            AssociationField::new('specialite'),
            AssociationField::new('formateurs', 'Formateurs')
    ->setFormTypeOptions([
        'by_reference' => false,
    ]),
        ];
    }
    
}
