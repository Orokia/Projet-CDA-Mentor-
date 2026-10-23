<?php

namespace App\Tests\Unit\Services;

use App\Entity\Formation;
use App\Entity\Formateur;
use App\Repository\FormationRepository;
use App\Repository\FormateurRepository;
use App\Service\HomeService;
use PHPUnit\Framework\TestCase;

class HomeServiceTest extends TestCase
{
    private FormationRepository $formationRepository;
    private FormateurRepository $formateurRepository;
    private HomeService $homeService;

    protected function setUp(): void
    {
        // Création des mocks des repositories
        $this->formationRepository = $this->createMock(
            FormationRepository::class
        );

        $this->formateurRepository = $this->createMock(
            FormateurRepository::class
        );

        // Injection des dépendances dans le service
        $this->homeService = new HomeService(
            $this->formationRepository,
            $this->formateurRepository
        );
    }

    public function testGetAllFormations(): void
    {
        // Préparation des formations simulées
        $formation1 = $this->createStub(Formation::class);
        $formation2 = $this->createStub(Formation::class);

        $formations = [$formation1, $formation2];

        // Configuration du repository
        $this->formationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($formations);

        // Les formateurs ne doivent pas être chargés
        $this->formateurRepository
            ->expects($this->never())
            ->method($this->anything());

        // Exécution du service
        $resultat = $this->homeService->getAllFormations();

        // Vérifications
        $this->assertCount(2, $resultat);
        $this->assertSame($formations, $resultat);
    }

    public function testGetAllFormationsRetourneUneListeVide(): void
    {
        // Le repository ne retourne aucune formation
        $this->formationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->formateurRepository
            ->expects($this->never())
            ->method($this->anything());

        // Exécution du service
        $resultat = $this->homeService->getAllFormations();

        // Vérification
        $this->assertSame([], $resultat);
    }

    public function testGetAllFormateurs(): void
    {
        // Préparation des formateurs simulés
        $formateur1 = $this->createStub(Formateur::class);
        $formateur2 = $this->createStub(Formateur::class);

        $formateurs = [$formateur1, $formateur2];

        // Configuration du repository
        $this->formateurRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($formateurs);

        // Les formations ne doivent pas être chargées
        $this->formationRepository
            ->expects($this->never())
            ->method($this->anything());

        // Exécution du service
        $resultat = $this->homeService->getAllFormateurs();

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

        $this->formationRepository
            ->expects($this->never())
            ->method($this->anything());

        // Exécution du service
        $resultat = $this->homeService->getAllFormateurs();

        // Vérification
        $this->assertSame([], $resultat);
    }
}
