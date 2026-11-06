<?php

namespace App\Tests\Integration\Services;

use App\Entity\User;
use App\Service\StudentProfileService;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StudentProfileServiceIntegrationTest extends KernelTestCase
{
    use EntitesDeTestTrait;

    private StudentProfileService $studentProfileService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->studentProfileService = static::getContainer()
            ->get(StudentProfileService::class);
    }

    private function relireUtilisateur(User $user): User
    {
        $id = $user->getId();
        $this->em()->clear();

        return $this->em()->find(User::class, $id);
    }

    public function testUpdateProfileEnregistreLeProfilEtLeCompte(): void
    {
        $user = $this->creerUtilisateur('amadou.diallo@exemple.fr');
        $student = $user->getStudent();

        $student
            ->setPrenom('Aïssata')
            ->setNom('Coulibaly')
            ->setEmail('aissata.coulibaly@exemple.fr');

        $this->studentProfileService->updateProfile($student, $user);

        $enBase = $this->relireUtilisateur($user);

        self::assertSame('Aïssata', $enBase->getPrenomUtilisateur());
        self::assertSame('Coulibaly', $enBase->getNomUtilisateur());
        self::assertSame('aissata.coulibaly@exemple.fr', $enBase->getEmail());
        self::assertNotNull($enBase->getUpdatedAt());

        self::assertSame('Aïssata', $enBase->getStudent()->getPrenom());
        self::assertSame('Coulibaly', $enBase->getStudent()->getNom());
    }

    public function testUpdatePhotoEstEnregistreeAvecLeProfil(): void
    {
        $user = $this->creerUtilisateur('amadou.diallo@exemple.fr');
        $student = $user->getStudent();

        // updatePhoto() ne fait pas de flush : c'est updateProfile() qui enregistre
        $this->studentProfileService->updatePhoto($student, $user, 'portrait-amadou-5c1e7a.jpg');
        $this->studentProfileService->updateProfile($student, $user);

        $enBase = $this->relireUtilisateur($user);

        self::assertSame('portrait-amadou-5c1e7a.jpg', $enBase->getPhotoUtilisateur());
        self::assertSame('portrait-amadou-5c1e7a.jpg', $enBase->getStudent()->getPhoto());
    }
}
