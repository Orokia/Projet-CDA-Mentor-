<?php

namespace App\Tests\Unit\Services\Admin;

use App\Entity\User;
use App\Service\Admin\UserCrudService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudServiceTest extends TestCase
{
    public function testHacherMotDePasseRemplaceLeHash(): void
    {
        $user = (new User())->setPassword('ancien-hash');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'NouveauMotDePasse123!')
            ->willReturn('nouveau-hash');

        (new UserCrudService($passwordHasher))
            ->hacherMotDePasse($user, 'NouveauMotDePasse123!');

        self::assertSame('nouveau-hash', $user->getPassword());
    }

    /**
     * Un champ laissé vide en modification conserve l'ancien mot de passe.
     */
    #[DataProvider('motsDePasseVides')]
    public function testHacherMotDePasseIgnoreUnMotDePasseVide(?string $motDePasse): void
    {
        $user = (new User())->setPassword('ancien-hash');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::never())->method('hashPassword');

        (new UserCrudService($passwordHasher))->hacherMotDePasse($user, $motDePasse);

        self::assertSame('ancien-hash', $user->getPassword());
    }

    public static function motsDePasseVides(): iterable
    {
        yield 'null' => [null];
        yield 'chaîne vide' => [''];
        yield 'espaces' => ['   '];
    }
}
