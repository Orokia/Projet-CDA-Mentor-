<?php

namespace App\Tests\Unit\Services;

use App\Entity\Formation;
use App\Entity\Formateur;
use App\Repository\FormationRepository;
use App\Repository\FormateurRepository;
use App\Service\FormationService;
use PHPUnit\Framework\TestCase;

class FormationServiceTest extends TestCase
{
    private FormationRepository $formationRepository;
    private FormateurRepository $formateurRepository;
    private FormationService $formationService;

    protected function setUp(): void
    {
        // Créer des mocks des repositories sans accéder à la base de données
        $this->formationRepository = $this->createMock(
            FormationRepository::class
        );

        $this->formateurRepository = $this->createMock(
            FormateurRepository::class
        );

        // Injecter les mocks dans le service à tester
        $this->formationService = new FormationService(
            $this->formationRepository,
            $this->formateurRepository
        );
    }

    /**
     * Vérifie que le service retourne toutes les formations.
     */
    public function testGetAllFormations(): void
    {
        // Préparer les données de test
        $formation1 = new Formation();
        $formation2 = new Formation();

        $formations = [$formation1, $formation2];

        // Simuler le comportement du repository
        $this->formationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($formations);

        $this->formateurRepository
            ->expects($this->never())
            ->method($this->anything());

        // Appeler la méthode du service
        $resultat = $this->formationService->getAllFormations();

        // Vérifier le résultat
        $this->assertSame($formations, $resultat);
        $this->assertCount(2, $resultat);
    }

    /**
     * Vérifie que le service retourne une formation existante.
     */
    public function testGetFormationById(): void
    {
        // Préparer une formation
        $formation = new Formation();
        $id = 1;

        // Simuler la recherche de la formation
        $this->formationRepository
            ->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($formation);

        $this->formateurRepository
            ->expects($this->never())
            ->method($this->anything());

        // Appeler le service
        $resultat = $this->formationService->getFormationById($id);

        // Vérifier que la formation retournée est la bonne
        $this->assertSame($formation, $resultat);
    }

    /**
     * Vérifie que le service retourne null si la formation n'existe pas.
     */
    public function testGetFormationByIdReturnsNullWhenNotFound(): void
    {
        $this->formationRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->formateurRepository
            ->expects($this->never())
            ->method($this->anything());

        $resultat = $this->formationService->getFormationById(999);

        $this->assertNull($resultat);
    }

    /**
     * Vérifie que le service retourne un formateur existant.
     */
    public function testGetFormateurById(): void
    {
        // Préparer un formateur
        $formateur = new Formateur();
        $id = 1;

        // Simuler la recherche du formateur
        $this->formateurRepository
            ->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($formateur);

        $this->formationRepository
            ->expects($this->never())
            ->method($this->anything());

        // Appeler le service
        $resultat = $this->formationService->getFormateurById($id);

        // Vérifier que le formateur retourné est le bon
        $this->assertSame($formateur, $resultat);
    }

    /**
     * Vérifie que le service retourne null si le formateur n'existe pas.
     */
    public function testGetFormateurByIdReturnsNullWhenNotFound(): void
    {
        $this->formateurRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->formationRepository
            ->expects($this->never())
            ->method($this->anything());

        $resultat = $this->formationService->getFormateurById(999);

        $this->assertNull($resultat);
    }
}
