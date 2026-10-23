<?php
namespace App\Tests\Integration\Services;

use App\Entity\Formation;
use App\Entity\Formateur;
use App\Service\HomeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class HomeServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private HomeService $homeService;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->homeService = $container->get(HomeService::class);
    }

    private function creerFormation(string $titre): Formation
    {
        $formation = new Formation();

        $formation
            ->setTitre($titre)
            ->setDescription('Description de test')
            ->setDuree(20)
            ->setPrix('199.99')
            ->setImage('formation-test.jpg');

        $this->entityManager->persist($formation);
        $this->entityManager->flush();

        return $formation;
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

    protected function tearDown(): void
    {
        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }
}
