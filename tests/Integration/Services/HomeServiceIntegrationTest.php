<?php

namespace App\Tests\Integration\Services;

use App\Service\HomeService;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class HomeServiceIntegrationTest extends KernelTestCase
{
    use EntitesDeTestTrait;

    private HomeService $homeService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->homeService = static::getContainer()->get(HomeService::class);
    }

    public function testGetAllFormations(): void
    {
        $formation = $this->creerFormation('Symfony');

        $formations = $this->homeService->getAllFormations();

        self::assertCount(1, $formations);
        self::assertSame($formation->getId(), $formations[0]->getId());
        self::assertSame('Symfony', $formations[0]->getTitre());
    }

    public function testGetAllFormateurs(): void
    {
        $formateur = $this->creerFormateur('aminata@example.test');

        $formateurs = $this->homeService->getAllFormateurs();

        self::assertCount(1, $formateurs);
        self::assertSame($formateur->getId(), $formateurs[0]->getId());
        self::assertSame('aminata@example.test', $formateurs[0]->getEmail());
    }
}
