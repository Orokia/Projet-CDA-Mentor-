<?php

namespace App\Tests\Integration\Services;

use App\Service\FormateurService;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FormateurServiceIntegrationTest extends KernelTestCase
{
    use EntitesDeTestTrait;

    private FormateurService $formateurService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->formateurService = static::getContainer()
            ->get(FormateurService::class);
    }

    public function testGetAllFormateurs(): void
    {
        $formateur = $this->creerFormateur('aminata.diallo@example.test');

        $formateurs = $this->formateurService->getAllFormateurs();

        self::assertCount(1, $formateurs);
        self::assertSame($formateur->getId(), $formateurs[0]->getId());
    }

    public function testGetFormateurById(): void
    {
        $formateur = $this->creerFormateur('formateur.recherche@example.test');

        $resultat = $this->formateurService->getFormateurById($formateur->getId());

        self::assertNotNull($resultat);
        self::assertSame($formateur->getId(), $resultat->getId());
    }

    public function testGetFormateurByIdReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->formateurService->getFormateurById(999999));
    }
}
