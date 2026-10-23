<?php

namespace App\Tests\Unit\Services;

use App\Entity\Student;
use App\Entity\User;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $userPasswordHasher;
    private RegistrationService $registrationService;

    protected function setUp(): void
    {
        // Création des mocks des dépendances
        $this->entityManager = $this->createMock(
            EntityManagerInterface::class
        );

        $this->userPasswordHasher = $this->createMock(
            UserPasswordHasherInterface::class
        );

        // Injection des dépendances dans le service
        $this->registrationService = new RegistrationService(
            $this->entityManager,
            $this->userPasswordHasher,
            new NullLogger()
        );
    }

    public function testRegister(): void
    {
        // Création d'un utilisateur
        $user = new User();
        $user->setNomUtilisateur('Diallo');
        $user->setPrenomUtilisateur('Amadou');
        $user->setEmail('amadou@example.com');

        // Simulation du hachage du mot de passe
        $this->userPasswordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'MotDePasse123!')
            ->willReturn('mot_de_passe_hache');

        // Vérification de l'enregistrement des entités
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->logicalOr(
                $this->identicalTo($user),
                $this->isInstanceOf(Student::class)
            ));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Exécution du service
        $resultat = $this->registrationService->register(
            $user,
            'MotDePasse123!'
        );

        // Vérification de l'utilisateur retourné
        $this->assertSame($user, $resultat);

        // Vérification du rôle par défaut
        $this->assertSame(['ROLE_USER'], $user->getRoles());

        // Vérification du mot de passe haché
        $this->assertSame(
            'mot_de_passe_hache',
            $user->getPassword()
        );

        // Vérification du profil étudiant associé
        $student = $user->getStudent();

        $this->assertInstanceOf(Student::class, $student);
        $this->assertSame('Diallo', $student->getNom());
        $this->assertSame('Amadou', $student->getPrenom());
        $this->assertSame('amadou@example.com', $student->getEmail());

        // Vérification des relations entre User et Student
        $this->assertSame($user, $student->getUser());
    }
}
