<?php
namespace App\Tests\Integration\Services;

use App\Entity\Formation;
use App\Service\FormationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FormationServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private FormationService $formationService;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->formationService = $container->get(FormationService::class);

        // Créer le schéma uniquement dans la base de test
        $metadata = $this->entityManager
            ->getMetadataFactory()
            ->getAllMetadata();

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function creerFormation(string $titre): Formation
    {
        $formation = new Formation();

        $formation
            ->setTitre($titre)
            ->setDescription('Description de la formation')
            ->setDuree(20)
            ->setPrix('199.99')
            ->setImage('formation-test.jpg');

        $this->entityManager->persist($formation);
        $this->entityManager->flush();

        return $formation;
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

        $resultat = $this->formationService
            ->getFormationById($formation->getId());

        self::assertNotNull($resultat);
        self::assertSame($formation->getId(), $resultat->getId());
        self::assertSame('PHP', $resultat->getTitre());
    }

    public function testGetFormationByIdReturnsNullWhenNotFound(): void
    {
        $resultat = $this->formationService->getFormationById(999999);

        self::assertNull($resultat);
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }
}
