<?php

namespace App\Controller\Admin;

use App\Entity\Specialite;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SpecialiteCrudController extends AbstractCrudController
{

    public function configureActions(Actions $actions): Actions
    {
        return $actions
        ->add(Crud:: PAGE_EDIT, Action::INDEX)
        ->add(Crud:: PAGE_INDEX, Action::DETAIL)
        ->add(Crud:: PAGE_EDIT, Action::DETAIL);

    }
    public static function getEntityFqcn(): string
    {
        return Specialite::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('type'),
            TextField::new('titre'),
           
        ];
    }
    
}
