<?php

namespace App\Tests\Unit\Services\Admin;

use App\Entity\Student;
use App\Entity\User;
use App\Service\Admin\StudentCrudService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class StudentCrudServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private StudentCrudService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $this->service = new StudentCrudService(
            $this->entityManager,
            $this->passwordHasher
        );
    }

    private function creerStudent(): Student
    {
        return (new Student())
            ->setNom('Diallo')
            ->setPrenom('Amadou');
    }

    public function testPreparerCreationCreeUnCompteVerifie(): void
    {
        $student = $this->creerStudent();

        $this->passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with(self::isInstanceOf(User::class), 'MotDePasse123!')
            ->willReturn('hash-test');

        $this->entityManager->expects(self::exactly(2))->method('persist');

        $user = $this->service->preparerCreation(
            $student,
            'amadou@example.test',
            'MotDePasse123!'
        );

        self::assertSame('amadou@example.test', $user->getEmail());
        self::assertSame('Diallo', $user->getNomUtilisateur());
        self::assertSame('Amadou', $user->getPrenomUtilisateur());
        self::assertSame('hash-test', $user->getPassword());
        self::assertTrue($user->isVerified());
        self::assertContains('ROLE_STUDENT', $user->getRoles());
        self::assertSame($student, $user->getStudent());
        self::assertSame($user, $student->getUser());
    }

    public function testPreparerCreationRefuseUnMotDePasseVide(): void
    {
        $this->passwordHasher->expects(self::never())->method('hashPassword');
        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le mot de passe de l’étudiant est obligatoire.');

        $this->service->preparerCreation($this->creerStudent(), 'amadou@example.test', '  ');
    }

    public function testPreparerModificationConserveLeMotDePasseSiVide(): void
    {
        $student = $this->creerStudent();
        $user = (new User())
            ->setEmail('ancien@example.test')
            ->setPassword('ancien-hash');
        $student->setUser($user);
        $user->setStudent($student);

        $this->passwordHasher->expects(self::never())->method('hashPassword');
        $this->entityManager->expects(self::exactly(2))->method('persist');

        $resultat = $this->service->preparerModification($student, null, '');

        self::assertSame($user, $resultat);
        self::assertSame('ancien@example.test', $user->getEmail());
        self::assertSame('ancien-hash', $user->getPassword());
        self::assertSame('Diallo', $user->getNomUtilisateur());
    }

    public function testPreparerModificationMetAJourEmailEtMotDePasse(): void
    {
        $student = $this->creerStudent();
        $user = (new User())
            ->setEmail('ancien@example.test')
            ->setPassword('ancien-hash');
        $student->setUser($user);
        $user->setStudent($student);

        $this->passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'Nouveau123!')
            ->willReturn('nouveau-hash');

        $this->entityManager->expects(self::exactly(2))->method('persist');

        $this->service->preparerModification($student, 'nouveau@example.test', 'Nouveau123!');

        self::assertSame('nouveau@example.test', $user->getEmail());
        self::assertSame('nouveau-hash', $user->getPassword());
    }

    public function testPreparerModificationCreeUnCompteManquant(): void
    {
        $student = $this->creerStudent();

        $this->passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->willReturn('hash-test');

        // Le nouveau compte est persisté à sa création, puis avec l'étudiant
        $this->entityManager->expects(self::exactly(3))->method('persist');

        $user = $this->service->preparerModification(
            $student,
            'amadou@example.test',
            'MotDePasse123!'
        );

        self::assertSame($user, $student->getUser());
        self::assertSame('amadou@example.test', $user->getEmail());
        self::assertSame('hash-test', $user->getPassword());
        self::assertTrue($user->isVerified());
    }

    public function testPreparerModificationSansCompteExigeUnEmail(): void
    {
        $this->entityManager->expects(self::never())->method('persist');
        $this->passwordHasher->expects(self::never())->method('hashPassword');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Un e-mail est obligatoire pour créer le compte étudiant.');

        $this->service->preparerModification($this->creerStudent(), null, 'MotDePasse123!');
    }

    public function testPreparerModificationSansCompteExigeUnMotDePasse(): void
    {
        $this->entityManager->expects(self::never())->method('persist');
        $this->passwordHasher->expects(self::never())->method('hashPassword');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Un mot de passe est obligatoire pour créer le compte étudiant.');

        $this->service->preparerModification($this->creerStudent(), 'amadou@example.test', null);
    }
}
