<?php

namespace App\Tests\Unit\Services;

use App\Dto\MentorFormateurInfo;
use App\Dto\MentorStudent;
use App\Dto\MentorUser;
use App\Entity\Formateur;
use App\Entity\Student;
use App\Entity\User;
use App\Service\MentorUserService;
use PHPUnit\Framework\TestCase;

/**
 * Même service que MentorUserServiceTest, mais avec de vraies entités
 * reliées entre elles (pas de mocks).
 */
final class MentorUserServiceEntitesTest extends TestCase
{
    private MentorUserService $service;

    protected function setUp(): void
    {
        $this->service = new MentorUserService();
    }

    private function creerUtilisateur(): User
    {
        return (new User())
            ->setEmail('orokia@example.test')
            ->setPassword('mot-de-passe-test')
            ->setRoles(['ROLE_USER'])
            ->setNomUtilisateur('Coulibali')
            ->setPrenomUtilisateur('Orokia')
            ->setPhotoUtilisateur('avatar.jpg');
    }

    public function testBuildUtilisateurSansProfil(): void
    {
        $user = $this->creerUtilisateur();

        $dto = $this->service->build($user);

        self::assertInstanceOf(MentorUser::class, $dto);
        self::assertSame($user->getId(), $dto->getIdCompte());
        self::assertSame('orokia@example.test', $dto->getEmail());
        self::assertSame($user->getRoles(), $dto->getRoles());
        self::assertSame('Coulibali', $dto->getNom());
        self::assertSame('Orokia', $dto->getPrenom());
        self::assertSame('avatar.jpg', $dto->getPhoto());
        self::assertNull($dto->getStudent());
        self::assertNull($dto->getFormateurInfo());
    }

    public function testBuildUtilisateurAvecProfilEtudiant(): void
    {
        $user = $this->creerUtilisateur();

        $student = (new Student())
            ->setNom('Coulibali')
            ->setPrenom('Orokia')
            ->setEmail('orokia@example.test')
            ->setPhoto('student.jpg');

        $user->setStudent($student);
        $student->setUser($user);

        $dto = $this->service->build($user);

        self::assertInstanceOf(MentorStudent::class, $dto->getStudent());
        self::assertSame($student->getId(), $dto->getStudent()->getId());
        self::assertSame('Coulibali', $dto->getStudent()->getNom());
        self::assertSame('Orokia', $dto->getStudent()->getPrenom());
        self::assertSame('student.jpg', $dto->getStudent()->getPhoto());
        self::assertNull($dto->getFormateurInfo());
    }

    public function testBuildUtilisateurAvecProfilFormateur(): void
    {
        $user = $this->creerUtilisateur();

        $formateur = (new Formateur())
            ->setNom('Diallo')
            ->setPrenom('Aminata')
            ->setEmail('aminata@example.test')
            ->setExperience('5 ans')
            ->setLangue('Français')
            ->setLocalisation('Paris')
            ->setVideoPresentation('presentation.mp4');

        // L'entité Formateur doit posséder setImage() et setCompteUtilisateur().
        $formateur->setImage('formateur.jpg');
        $user->setFormateur($formateur);

        $dto = $this->service->build($user);

        self::assertInstanceOf(
            MentorFormateurInfo::class,
            $dto->getFormateurInfo()
        );

        $info = $dto->getFormateurInfo();

        self::assertSame($formateur->getId(), $info->getId());
        self::assertSame('5 ans', $info->getExperience());
        self::assertSame('Français', $info->getLangue());
        self::assertSame('Paris', $info->getLocalisation());
        self::assertSame(
            $formateur->getCreatedAt(),
            $info->getCreatedAt()
        );
        self::assertSame(
            'presentation.mp4',
            $info->getVideoPresentation()
        );
        self::assertSame('formateur.jpg', $info->getImage());
        self::assertNull($dto->getStudent());
    }
}
