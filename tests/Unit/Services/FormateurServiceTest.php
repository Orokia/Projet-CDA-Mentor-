<?php

namespace App\Tests\Unit\Services;

use App\Entity\Formateur;
use App\Repository\FormateurRepository;
use App\Service\FormateurService;
use PHPUnit\Framework\TestCase;

class FormateurServiceTest extends TestCase
{
    private FormateurRepository $formateurRepository;
    private FormateurService $formateurService;

    protected function setUp(): void
    {
        // Création d'un mock du repository
        $this->formateurRepository = $this->createMock(
            FormateurRepository::class
        );

        // Injection du mock dans le service
        $this->formateurService = new FormateurService(
            $this->formateurRepository
        );
    }

    public function testGetAllFormateurs(): void
    {
        // Préparation des données simulées
        $formateur1 = $this->createMock(Formateur::class);
        $formateur2 = $this->createMock(Formateur::class);

        $formateurs = [$formateur1, $formateur2];

        // Configuration du repository
        $this->formateurRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($formateurs);

        // Exécution du service
        $resultat = $this->formateurService->getAllFormateurs();

        // Vérifications
        $this->assertCount(2, $resultat);
        $this->assertSame($formateurs, $resultat);
    }

    public function testGetAllFormateursRetourneUneListeVide(): void
    {
        // Le repository ne retourne aucun formateur
        $this->formateurRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        // Exécution du service
        $resultat = $this->formateurService->getAllFormateurs();

        // Vérification
        $this->assertSame([], $resultat);
    }

    public function testGetFormateurById(): void
    {
        // Préparation d'un formateur simulé
        $formateur = $this->createMock(Formateur::class);

        // Configuration du repository
        $this->formateurRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($formateur);

        // Exécution du service
        $resultat = $this->formateurService->getFormateurById(1);

        // Vérification
        $this->assertSame($formateur, $resultat);
    }

    public function testGetFormateurByIdInexistant(): void
    {
        // Le repository ne trouve aucun formateur
        $this->formateurRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        // Exécution du service
        $resultat = $this->formateurService->getFormateurById(999);

        // Vérification
        $this->assertNull($resultat);
    }
}
