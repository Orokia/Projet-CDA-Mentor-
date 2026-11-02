<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ReservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reservation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('student', 'Étudiant')
            ->setRequired(true);

        yield AssociationField::new('formation', 'Formation')
            ->setRequired(true);

        yield AssociationField::new('formateur', 'Formateur')
            ->setRequired(true);

        yield DateTimeField::new('dateReservation', 'Date de réservation')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setRequired(true);

        yield DateTimeField::new('dateHeureDebut', 'Début de la session')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setRequired(true);

        yield DateTimeField::new('dateHeureFin', 'Fin de la session')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setRequired(true);

        yield ChoiceField::new('statut', 'Statut')
            ->setChoices([
                'En attente' => 'EN_ATTENTE',
                'Confirmée' => 'CONFIRMEE',
                'Annulée' => 'ANNULEE',
                'Terminée' => 'TERMINEE',
            ])
            ->renderExpanded(false)
            ->setRequired(true);

        yield AssociationField::new('paiement', 'Paiement')
            ->setRequired(false);

        yield TextField::new('lienVisio', 'Lien visioconférence')
            ->hideOnIndex()
            ->setRequired(false);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Réservation')
            ->setEntityLabelInPlural('Réservations')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Gestion des réservations'
            )
            ->setPageTitle(
                Crud::PAGE_NEW,
                'Créer une réservation'
            )
            ->setPageTitle(
                Crud::PAGE_EDIT,
                'Modifier une réservation'
            );
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}