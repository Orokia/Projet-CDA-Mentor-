<?php

namespace App\Tests\Unit\Services;

use App\Entity\Student;
use App\Entity\User;
use App\Service\StudentProfileService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class StudentProfileServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private StudentProfileService $studentProfileService;

    protected function setUp(): void
    {
        // Création du mock de l'EntityManager
        $this->entityManager = $this->createMock(
            EntityManagerInterface::class
        );

        // Injection de la dépendance dans le service
        $this->studentProfileService = new StudentProfileService(
            $this->entityManager
        );
    }

    public function testUpdateProfile(): void
    {
        // Création des entités
        $student = new Student();
        $student->setPrenom('Amadou');
        $student->setNom('Diallo');
        $student->setEmail('amadou@example.com');

        $user = new User();

        // Vérification de l'enregistrement des modifications
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Exécution du service
        $this->studentProfileService->updateProfile(
            $student,
            $user
        );

        // Vérification de la synchronisation des informations
        $this->assertSame(
            'Amadou',
            $user->getPrenomUtilisateur()
        );

        $this->assertSame(
            'Diallo',
            $user->getNomUtilisateur()
        );

        $this->assertSame(
            'amadou@example.com',
            $user->getEmail()
        );

        // Vérification de la date de modification
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $user->getUpdatedAt()
        );
    }

    public function testUpdatePhoto(): void
    {
        // Création des entités
        $student = new Student();
        $user = new User();

        $filename = 'photo-profil.jpg';

        // updatePhoto() ne doit pas appeler flush().
        // L'attente doit être déclarée AVANT l'appel pour être vérifiée.
        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        // Exécution du service
        $this->studentProfileService->updatePhoto(
            $student,
            $user,
            $filename
        );

        // Vérification de la synchronisation des photos
        $this->assertSame(
            $filename,
            $student->getPhoto()
        );

        $this->assertSame(
            $filename,
            $user->getPhotoUtilisateur()
        );
    }
}
