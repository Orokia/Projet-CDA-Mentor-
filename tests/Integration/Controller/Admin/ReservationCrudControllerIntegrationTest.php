<?php

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReservationCrudControllerIntegrationTest extends KernelTestCase
{
    public function testReservationEntityHasExpectedAssociations(): void
    {
        self::bootKernel();

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $metadata = $entityManager->getClassMetadata(
            Reservation::class
        );

        self::assertContains(
            'student',
            $metadata->getAssociationNames()
        );

        self::assertContains(
            'formation',
            $metadata->getAssociationNames()
        );

        self::assertContains(
            'formateur',
            $metadata->getAssociationNames()
        );

        self::assertContains(
            'paiement',
            $metadata->getAssociationNames()
        );
    }

    public function testReservationEntityMetadataIsLoaded(): void
    {
        self::bootKernel();

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $metadata = $entityManager->getClassMetadata(
            Reservation::class
        );

        self::assertSame(
            Reservation::class,
            $metadata->getName()
        );
    }
}
