<?php

namespace App\Tests\Integration\Services;

use App\Entity\Formateur;
use App\Service\FormateurService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FormateurServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private FormateurService $formateurService;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $container = static::getContainer();

        $this->entityManager = $container->get(
            EntityManagerInterface::class
        );

        $this->formateurService = $container->get(
            FormateurService::class
        );
    }

    private function creerFormateur(string $email): Formateur
    {
        $formateur = new Formateur();

        $formateur
            ->setNom('Diallo')
            ->setPrenom('Aminata')
            ->setEmail($email)
            ->setExperience('5 ans')
            ->setLangue('Français')
            ->setLocalisation('Paris')
            ->setVideoPresentation('presentation.mp4');

        $this->entityManager->persist($formateur);
        $this->entityManager->flush();

        return $formateur;
    }

    public function testGetAllFormateurs(): void
    {
        $formateur = $this->creerFormateur(
            'aminata.diallo@example.test'
        );

        $formateurs = $this->formateurService->getAllFormateurs();

        self::assertCount(1, $formateurs);
        self::assertSame(
            $formateur->getId(),
            $formateurs[0]->getId()
        );
    }

    public function testGetFormateurById(): void
    {
        $formateur = $this->creerFormateur(
            'formateur.recherche@example.test'
        );

        $resultat = $this->formateurService->getFormateurById(
            $formateur->getId()
        );

        self::assertNotNull($resultat);
        self::assertSame(
            $formateur->getId(),
            $resultat->getId()
        );
    }

    protected function tearDown(): void
    {
        if (
            isset($this->entityManager)
            && $this->entityManager->isOpen()
        ) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }
}
