<?php

namespace App\Tests\Unit\Services;

use App\Entity\Student;
use App\Entity\User;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

    // Un rôle envoyé dans le formulaire ne doit jamais être conservé
    public function testRegisterRemplaceLesRolesExistantsParRoleUser(): void
    {
        $user = $this->creerUtilisateur();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_FORMATEUR']);

        $this->userPasswordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('$2y$04$hachageSimule');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->registrationService->register($user, 'Printemps2026!');

        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testRegisterHacheLeMotDePasseAvantLEnregistrement(): void
    {
        $user = $this->creerUtilisateur();
        $appels = [];

        $this->userPasswordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturnCallback(static function () use (&$appels): string {
                $appels[] = 'hashPassword';

                return '$2y$04$hachageSimule';
            });

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (object $entite) use (&$appels, $user): void {
                // Le mot de passe en clair ne doit jamais atteindre la base
                self::assertSame('$2y$04$hachageSimule', $user->getPassword());
                $appels[] = 'persist ' . (new \ReflectionClass($entite))->getShortName();
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willReturnCallback(static function () use (&$appels): void {
                $appels[] = 'flush';
            });

        $this->registrationService->register($user, 'Printemps2026!');

        $this->assertSame(
            ['hashPassword', 'persist User', 'persist Student', 'flush'],
            $appels
        );
        $this->assertNotSame('Printemps2026!', $user->getPassword());
    }

    public function testRegisterJournaliseLaCreationDuCompte(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Nouveau compte étudiant créé.',
                $this->arrayHasKey('userId')
            );

        $this->userPasswordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('$2y$04$hachageSimule');

        // Le journal est écrit après l'enregistrement
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new RegistrationService(
            $this->entityManager,
            $this->userPasswordHasher,
            $logger
        );

        $service->register($this->creerUtilisateur(), 'Printemps2026!');
    }

    private function creerUtilisateur(): User
    {
        return (new User())
            ->setNomUtilisateur('Lefèvre')
            ->setPrenomUtilisateur('Camille')
            ->setEmail('camille.lefevre@exemple.fr');
    }
}
