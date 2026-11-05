<?php

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\DisponibiliteCrudController;
use App\Controller\Admin\Field\JoursSemaineField;
use App\Entity\Disponibilite;
use App\Form\JoursSemaineType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
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

    public function testJoursSemaineUtiliseLeFormulaireJourStatut(): void
    {
        $controller = new DisponibiliteCrudController();
        $champs = array_values(array_filter(
            iterator_to_array($controller->configureFields(Crud::PAGE_EDIT), false),
            static fn ($field): bool => 'joursSemaine' === $field->getAsDto()->getProperty()
        ));

        self::assertCount(1, $champs);
        // Champ dédié : un Field générique deviendrait un ArrayField (colonne JSON)
        self::assertInstanceOf(JoursSemaineField::class, $champs[0]);
        self::assertSame(JoursSemaineType::class, $champs[0]->getAsDto()->getFormType());
    }

    public function testJoursSemaineAfficheLeJourEtSonStatut(): void
    {
        $formater = $this->champJoursSemaine()->getFormatValueCallable();

        self::assertNotNull($formater);
        self::assertSame(
            'Lundi (disponible), Mardi (plus disponible)',
            $formater(['lundi' => 'disponible', 'mardi' => 'plus disponible'], new Disponibilite())
        );
        self::assertSame('Aucun jour', $formater([], new Disponibilite()));
    }

    private function champJoursSemaine(): FieldDto
    {
        $controller = new DisponibiliteCrudController();

        foreach ($controller->configureFields(Crud::PAGE_EDIT) as $field) {
            $dto = $field->getAsDto();

            if ('joursSemaine' === $dto->getProperty()) {
                return $dto;
            }
        }

        self::fail('Le champ joursSemaine est absent.');
    }
}
