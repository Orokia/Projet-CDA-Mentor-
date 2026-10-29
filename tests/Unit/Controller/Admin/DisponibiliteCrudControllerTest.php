<?php

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\DisponibiliteCrudController;
use App\Entity\Disponibilite;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use PHPUnit\Framework\TestCase;

class DisponibiliteCrudControllerTest extends TestCase
{
    public function testGetEntityFqcnReturnsDisponibilite(): void
    {
        self::assertSame(
            Disponibilite::class,
            DisponibiliteCrudController::getEntityFqcn()
        );
    }

    public function testConfigureFieldsReturnsExpectedFields(): void
    {
        $controller = new DisponibiliteCrudController();

        $fields = iterator_to_array(
            $controller->configureFields(Crud::PAGE_INDEX)
        );

        $propertyNames = array_map(
            static fn ($field) => $field->getAsDto()->getProperty(),
            $fields
        );

        self::assertContains('formateur', $propertyNames);
        self::assertContains('formation', $propertyNames);
        self::assertContains('dateDebut', $propertyNames);
        self::assertContains('dateFin', $propertyNames);
        self::assertContains('heureDebut', $propertyNames);
        self::assertContains('heureFin', $propertyNames);
        self::assertContains('joursSemaine', $propertyNames);
    }
}
