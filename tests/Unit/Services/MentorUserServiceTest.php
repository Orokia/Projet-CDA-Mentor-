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

class MentorUserServiceTest extends TestCase
{
    private MentorUserService $service;

    protected function setUp(): void
    {
        $this->service = new MentorUserService();
    }

    public function testBuildReturnsAccountInformation(): void
    {

       
        $user = $this->createStub(User::class);

        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getRoles')->willReturn(['ROLE_STUDENT']);
        $user->method('getNomUtilisateur')->willReturn('Coulibali');
        $user->method('getPrenomUtilisateur')->willReturn('Orokia');
        $user->method('getPhotoUtilisateur')->willReturn('photo.jpg');
        $user->method('getStudent')->willReturn(null);
        $user->method('getFormateur')->willReturn(null);

        $result = $this->service->build($user);

        $this->assertInstanceOf(MentorUser::class, $result);
        $this->assertSame(1, $result->getIdCompte());
        $this->assertSame('test@example.com', $result->getEmail());
        $this->assertSame(['ROLE_STUDENT'], $result->getRoles());
        $this->assertSame('Coulibali', $result->getNom());
        $this->assertSame('Orokia', $result->getPrenom());
        $this->assertSame('photo.jpg', $result->getPhoto());
        $this->assertNull($result->getStudent());
        $this->assertNull($result->getFormateurInfo());
    }

    public function testBuildCreatesStudentDtoWhenStudentExists(): void
    {
        $student = $this->createStub(Student::class);

        $student->method('getId')->willReturn(10);
        $student->method('getNom')->willReturn('Diallo');
        $student->method('getPrenom')->willReturn('Aminata');
        $student->method('getPhoto')->willReturn('student.jpg');

        $user = $this->createStub(User::class);

        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn('student@example.com');
        $user->method('getRoles')->willReturn(['ROLE_STUDENT']);
        $user->method('getNomUtilisateur')->willReturn('Diallo');
        $user->method('getPrenomUtilisateur')->willReturn('Aminata');
        $user->method('getPhotoUtilisateur')->willReturn(null);
        $user->method('getStudent')->willReturn($student);
        $user->method('getFormateur')->willReturn(null);

        $result = $this->service->build($user);

        $this->assertInstanceOf(MentorStudent::class, $result->getStudent());
        $this->assertSame(10, $result->getStudent()->getId());
        $this->assertSame('Diallo', $result->getStudent()->getNom());
        $this->assertSame('Aminata', $result->getStudent()->getPrenom());
        $this->assertSame('student.jpg', $result->getStudent()->getPhoto());
        $this->assertNull($result->getFormateurInfo());
    }

    public function testBuildCreatesFormateurDtoWhenFormateurExists(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-15 10:00:00');

        $formateur = $this->createStub(Formateur::class);

        $formateur->method('getId')->willReturn(20);
        $formateur->method('getExperience')->willReturn('5 ans');
        $formateur->method('getLangue')->willReturn('Français');
        $formateur->method('getLocalisation')->willReturn('Paris');
        $formateur->method('getCreatedAt')->willReturn($createdAt);
        $formateur->method('getVideoPresentation')->willReturn('presentation.mp4');
        $formateur->method('getImage')->willReturn('formateur.jpg');

        $user = $this->createStub(User::class);

        $user->method('getId')->willReturn(2);
        $user->method('getEmail')->willReturn('formateur@example.com');
        $user->method('getRoles')->willReturn(['ROLE_FORMATEUR']);
        $user->method('getNomUtilisateur')->willReturn('Traoré');
        $user->method('getPrenomUtilisateur')->willReturn('Moussa');
        $user->method('getPhotoUtilisateur')->willReturn(null);
        $user->method('getStudent')->willReturn(null);
        $user->method('getFormateur')->willReturn($formateur);

        $result = $this->service->build($user);

        $this->assertInstanceOf(
            MentorFormateurInfo::class,
            $result->getFormateurInfo()
        );

        $this->assertSame(20, $result->getFormateurInfo()->getId());
        $this->assertSame('5 ans', $result->getFormateurInfo()->getExperience());
        $this->assertSame('Français', $result->getFormateurInfo()->getLangue());
        $this->assertSame('Paris', $result->getFormateurInfo()->getLocalisation());
        $this->assertSame($createdAt, $result->getFormateurInfo()->getCreatedAt());
        $this->assertSame(
            'presentation.mp4',
            $result->getFormateurInfo()->getVideoPresentation()
        );
        $this->assertSame(
            'formateur.jpg',
            $result->getFormateurInfo()->getImage()
        );

        $this->assertNull($result->getStudent());
    }
}
