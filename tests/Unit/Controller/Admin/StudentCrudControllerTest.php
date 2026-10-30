<?php

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\StudentCrudController;
use App\Entity\Student;
use App\Entity\User;
use App\Service\Admin\StudentCrudService;
use App\Service\Admin\UserCrudService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use PHPUnit\Framework\TestCase;

final class StudentCrudControllerTest extends TestCase
{
    private StudentCrudController $controller;

   protected function setUp(): void
{
    $service = $this->createStub(
        UserCrudService::class
    );

    $this->controller = new StudentCrudController(
        $service
    );
}

    /**
     * Vérifie que le contrôleur admin gère bien l'entité Student.
     */
    public function testGetEntityFqcnReturnsStudent(): void
    {
        self::assertSame(
            User::class,
            StudentCrudController::getEntityFqcn()
        );
    }

    /**
     * Vérifie que les champs attendus sont configurés.
     */
    public function testConfigureFieldsContainsExpectedFields(): void
    {
        $fields = iterator_to_array(
            $this->controller->configureFields(Crud::PAGE_NEW)
        );

        $properties = [];

        foreach ($fields as $field) {
            $properties[] = $field->getAsDto()->getProperty();
        }

        self::assertContains('id', $properties);
        self::assertContains('email', $properties);
        self::assertContains('password', $properties);
        self::assertContains('nomUtilisateur', $properties);
        self::assertContains('prenomUtilisateur', $properties);
        self::assertContains('photoUtilisateur', $properties);
        self::assertNotContains('age', $properties);
    }

    /**
     * Vérifie que le champ password est présent à la création.
     */
    public function testPasswordFieldExistsOnCreation(): void
    {
        self::assertNotNull(
            $this->findField('password', Crud::PAGE_NEW)
        );
    }

    /**
     * Vérifie que le champ password est présent à la modification.
     */
    public function testPasswordFieldExistsOnEdit(): void
    {
        self::assertNotNull(
            $this->findField('password', Crud::PAGE_EDIT)
        );
    }

    private function findField(
        string $property,
        string $pageName
    ): ?object {
        foreach ($this->controller->configureFields($pageName) as $field) {
            $dto = $field->getAsDto();

            if ($dto->getProperty() === $property) {
                return $dto;
            }
        }

        return null;
    }
}