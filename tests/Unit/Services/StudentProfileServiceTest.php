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

    public function testUpdateProfileRemplaceLesAnciennesInformationsDuCompte(): void
    {
        $user = (new User())
            ->setPrenomUtilisateur('Amadou')
            ->setNomUtilisateur('Diallo')
            ->setEmail('amadou.diallo@exemple.fr');
        $user->setUpdatedAt(new \DateTimeImmutable('2025-01-15 08:30:00'));

        $student = (new Student())
            ->setPrenom('Fatoumata')
            ->setNom('Keïta')
            ->setEmail('fatoumata.keita@exemple.fr');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $avantMiseAJour = new \DateTimeImmutable();

        $this->studentProfileService->updateProfile($student, $user);

        $this->assertSame('Fatoumata', $user->getPrenomUtilisateur());
        $this->assertSame('Keïta', $user->getNomUtilisateur());
        $this->assertSame('fatoumata.keita@exemple.fr', $user->getEmail());

        // L'ancienne date de modification est remplacée par la date courante
        $this->assertGreaterThanOrEqual($avantMiseAJour, $user->getUpdatedAt());
    }

    public function testUpdatePhotoRemplaceUnePhotoExistante(): void
    {
        $student = (new Student())->setPhoto('ancienne-photo-3f2a9c.jpg');
        $user = (new User())->setPhotoUtilisateur('ancienne-photo-3f2a9c.jpg');

        $this->entityManager
            ->expects($this->never())
            ->method($this->anything());

        $this->studentProfileService->updatePhoto(
            $student,
            $user,
            'portrait-fatoumata-8b41d0.png'
        );

        $this->assertSame('portrait-fatoumata-8b41d0.png', $student->getPhoto());
        $this->assertSame('portrait-fatoumata-8b41d0.png', $user->getPhotoUtilisateur());
    }
}
