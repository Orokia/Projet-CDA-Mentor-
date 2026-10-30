<?php

namespace App\Tests\Integration\Services;

use App\Service\FormationService;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FormationServiceIntegrationTest extends KernelTestCase
{
    use EntitesDeTestTrait;

    private FormationService $formationService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->formationService = static::getContainer()
            ->get(FormationService::class);
    }

    public function testGetAllFormations(): void
    {
        $formation = $this->creerFormation('Symfony');

        $formations = $this->formationService->getAllFormations();

        self::assertCount(1, $formations);
        self::assertSame($formation->getId(), $formations[0]->getId());
        self::assertSame('Symfony', $formations[0]->getTitre());
    }

    public function testGetFormationById(): void
    {
        $formation = $this->creerFormation('PHP');

        $resultat = $this->formationService->getFormationById($formation->getId());

        self::assertNotNull($resultat);
        self::assertSame($formation->getId(), $resultat->getId());
        self::assertSame('PHP', $resultat->getTitre());
    }

    public function testGetFormationByIdReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->formationService->getFormationById(999999));
    }

    public function testGetFormateurById(): void
    {
        $formateur = $this->creerFormateur();

        $resultat = $this->formationService->getFormateurById($formateur->getId());

        self::assertSame($formateur->getId(), $resultat?->getId());
    }
}
