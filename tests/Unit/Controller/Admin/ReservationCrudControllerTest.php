<?php

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\ReservationCrudController;
use App\Entity\Reservation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use PHPUnit\Framework\TestCase;

final class ReservationCrudControllerTest extends TestCase
{
    private ReservationCrudController $controller;

    protected function setUp(): void
    {
        $this->controller = new ReservationCrudController();
    }

    public function testGetEntityFqcnReturnsReservation(): void
    {
        self::assertSame(
            Reservation::class,
            ReservationCrudController::getEntityFqcn()
        );
    }

    public function testConfigureFieldsContainsExpectedFields(): void
    {
        $fields = iterator_to_array(
            $this->controller->configureFields(Crud::PAGE_NEW)
        );

        $properties = [];

        foreach ($fields as $field) {
            $properties[] = $field->getAsDto()->getProperty();
        }

        self::assertContains('student', $properties);
        self::assertContains('formation', $properties);
        self::assertContains('formateur', $properties);
        self::assertContains('dateReservation', $properties);
        self::assertContains('dateHeureDebut', $properties);
        self::assertContains('dateHeureFin', $properties);
        self::assertContains('statut', $properties);
        self::assertContains('paiement', $properties);
        self::assertContains('lienVisio', $properties);
    }

    public function testConfigureFieldsContainsNineFields(): void
    {
        $fields = iterator_to_array(
            $this->controller->configureFields(Crud::PAGE_INDEX)
        );

        self::assertCount(9, $fields);
    }

   
}
