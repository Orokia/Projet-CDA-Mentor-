<?php

namespace App\Tests\Integration\Services;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RegistrationService;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Inscription avec le vrai hacheur de mots de passe et la base SQLite.
 */
final class RegistrationServiceIntegrationTest extends KernelTestCase
{
    use EntitesDeTestTrait;

    private RegistrationService $registrationService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->registrationService = static::getContainer()
            ->get(RegistrationService::class);
    }

    private function inscrire(string $email = 'lea.martin@exemple.fr'): User
    {
        $user = (new User())
            ->setNomUtilisateur('Martin')
            ->setPrenomUtilisateur('Léa')
            ->setEmail($email);

        return $this->registrationService->register($user, 'Automne2026!');
    }

    public function testRegisterEnregistreLeCompteEtLeProfilEtudiant(): void
    {
        $user = $this->inscrire();

        self::assertNotNull($user->getId());

        // Relecture depuis la base, sans le cache de l'EntityManager
        $this->em()->clear();
        $enBase = static::getContainer()
            ->get(UserRepository::class)
            ->findOneBy(['email' => 'lea.martin@exemple.fr']);

        self::assertNotNull($enBase);
        self::assertSame(['ROLE_USER'], $enBase->getRoles());
        self::assertFalse($enBase->isVerified());
        self::assertNotNull($enBase->getCreatedAt());

        $student = $enBase->getStudent();
        self::assertNotNull($student);
        self::assertNotNull($student->getId());
        self::assertSame('Martin', $student->getNom());
        self::assertSame('Léa', $student->getPrenom());
        self::assertSame('lea.martin@exemple.fr', $student->getEmail());
        self::assertSame($enBase->getId(), $student->getUser()->getId());
    }

    public function testRegisterStockeUnMotDePasseHacheVerifiable(): void
    {
        $this->inscrire();

        $this->em()->clear();
        $enBase = static::getContainer()
            ->get(UserRepository::class)
            ->findOneBy(['email' => 'lea.martin@exemple.fr']);

        self::assertNotSame('Automne2026!', $enBase->getPassword());

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($enBase, 'Automne2026!'));
        self::assertFalse($hasher->isPasswordValid($enBase, 'automne2026!'));
    }

    public function testDeuxInscriptionsCreentDeuxProfilsDistincts(): void
    {
        $premier = $this->inscrire('lea.martin@exemple.fr');
        $second = $this->inscrire('hugo.bernard@exemple.fr');

        self::assertNotSame($premier->getStudent()->getId(), $second->getStudent()->getId());
        self::assertSame(2, static::getContainer()->get(UserRepository::class)->count([]));
    }
}
