<?php

namespace App\Tests\Unit\Services\Admin;

use App\Entity\Formateur;
use App\Entity\User;
use App\Service\Admin\FormateurCrudService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FormateurCrudServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private FormateurCrudService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(
            EntityManagerInterface::class
        );

        $this->passwordHasher = $this->createMock(
            UserPasswordHasherInterface::class
        );

        $this->service = new FormateurCrudService(
            $this->entityManager,
            $this->passwordHasher
        );
    }

    public function testPreparerCreationCreeUnCompteFormateur(): void
    {
        $formateur = new Formateur();
        $formateur->setEmail('formateur@example.com');
        $formateur->setNom('Diallo');
        $formateur->setPrenom('Aminata');
        $formateur->setPassword('MotDePasse123!');

        $this->passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with(
                self::isInstanceOf(User::class),
                'MotDePasse123!'
            )
            ->willReturn('hash-test');

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(User::class));

        $user = $this->service->preparerCreation($formateur);

        self::assertSame('formateur@example.com', $user->getEmail());
        self::assertSame('Diallo', $user->getNomUtilisateur());
        self::assertSame('Aminata', $user->getPrenomUtilisateur());
        self::assertSame(
            ['ROLE_FORMATEUR', 'ROLE_USER'],
            $user->getRoles()
        );
        self::assertSame('hash-test', $user->getPassword());
        self::assertSame('hash-test', $formateur->getPassword());

        self::assertSame($user, $formateur->getCompteUtilisateur());
        self::assertSame($formateur, $user->getFormateur());
    }

    public function testPreparerCreationRefuseUnMotDePasseVide(): void
    {
        $formateur = new Formateur();
        $formateur->setPassword('');

        $this->passwordHasher
            ->expects(self::never())
            ->method('hashPassword');

        $this->entityManager
            ->expects(self::never())
            ->method('persist');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Le mot de passe du formateur est obligatoire.'
        );

        $this->service->preparerCreation($formateur);
    }

    public function testPreparerModificationSynchroniseLeCompte(): void
    {
        $formateur = new Formateur();
        $formateur->setEmail('nouveau@example.com');
        $formateur->setNom('Traore');
        $formateur->setPrenom('Mariam');
        $formateur->setPassword('');

        $user = new User();
        $user->setPassword('ancien-hash');

        $formateur->setCompteUtilisateur($user);
        $user->setFormateur($formateur);

        $this->entityManager
            ->expects(self::never())
            ->method('persist');

        $this->passwordHasher
            ->expects(self::never())
            ->method('hashPassword');

        $result = $this->service->preparerModification($formateur);

        self::assertSame($user, $result);
        self::assertSame('nouveau@example.com', $user->getEmail());
        self::assertSame('Traore', $user->getNomUtilisateur());
        self::assertSame('Mariam', $user->getPrenomUtilisateur());
        self::assertSame(
            ['ROLE_FORMATEUR', 'ROLE_USER'],
            $user->getRoles()
        );
        self::assertSame('ancien-hash', $formateur->getPassword());
        self::assertSame('ancien-hash', $user->getPassword());
    }

    public function testPreparerModificationHacheUnNouveauMotDePasse(): void
    {
        $formateur = new Formateur();
        $formateur->setEmail('formateur@example.com');
        $formateur->setNom('Diallo');
        $formateur->setPrenom('Aminata');
        $formateur->setPassword('NouveauMotDePasse123!');

        $user = new User();
        $user->setPassword('ancien-hash');

        $formateur->setCompteUtilisateur($user);
        $user->setFormateur($formateur);

        $this->passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'NouveauMotDePasse123!')
            ->willReturn('nouveau-hash');

        $this->entityManager
            ->expects(self::never())
            ->method('persist');

        $result = $this->service->preparerModification($formateur);

        self::assertSame($user, $result);
        self::assertSame('nouveau-hash', $user->getPassword());
        self::assertSame('nouveau-hash', $formateur->getPassword());
    }

    public function testPreparerModificationCreeUnCompteManquant(): void
    {
        $formateur = new Formateur();
        $formateur->setEmail('formateur@example.com');
        $formateur->setNom('Diallo');
        $formateur->setPrenom('Aminata');
        $formateur->setPassword('');

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(User::class));

        $this->passwordHasher
            ->expects(self::never())
            ->method('hashPassword');

        $user = $this->service->preparerModification($formateur);

        self::assertSame($user, $formateur->getCompteUtilisateur());
        self::assertSame($formateur, $user->getFormateur());
        self::assertSame(
            ['ROLE_FORMATEUR', 'ROLE_USER'],
            $user->getRoles()
        );
        self::assertSame('formateur@example.com', $user->getEmail());
    }
}
