<?php

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserRepositoryIntegrationTest extends KernelTestCase
{
    use EntitesDeTestTrait;

    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = static::getContainer()->get(UserRepository::class);
    }

    // Appelé par Symfony à la connexion quand l'algorithme de hachage évolue
    public function testUpgradePasswordEnregistreLeNouveauHash(): void
    {
        $user = $this->creerUtilisateur('moussa.traore@exemple.fr');
        $id = $user->getId();

        $this->repository->upgradePassword($user, '$argon2id$v=19$m=65536,t=4,p=1$nouveauHash');

        $this->em()->clear();
        self::assertSame(
            '$argon2id$v=19$m=65536,t=4,p=1$nouveauHash',
            $this->em()->find(User::class, $id)->getPassword()
        );
    }

    public function testUpgradePasswordRefuseUnAutreTypeDUtilisateur(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->repository->upgradePassword(
            new InMemoryUser('moussa.traore@exemple.fr', 'ancien-hash'),
            'nouveau-hash'
        );
    }
}
