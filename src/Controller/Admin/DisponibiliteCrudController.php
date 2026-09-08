<?php

namespace App\Controller\Admin;

use App\Entity\Disponibilite;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;

class DisponibiliteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Disponibilite::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
{
    yield AssociationField::new('formateur', 'Formateur');
    yield AssociationField::new('formation', 'Formation');

    yield DateField::new('dateDebut', 'Date de début');
    yield DateField::new('dateFin', 'Date de fin');

    yield TimeField::new('heureDebut', 'Heure de début');
    yield TimeField::new('heureFin', 'Heure de fin');

    yield ChoiceField::new('joursSemaine', 'Jours de la semaine')
        ->setChoices([
            'Lundi' => 'lundi',
            'Mardi' => 'mardi',
            'Mercredi' => 'mercredi',
            'Jeudi' => 'jeudi',
            'Vendredi' => 'vendredi',
            'Samedi' => 'samedi',
            'Dimanche' => 'dimanche',
        ])
        ->allowMultipleChoices()
        ->renderExpanded();
}
}
