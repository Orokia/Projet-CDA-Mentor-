<?php

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\User;
use App\Service\ReservationService;
use App\Tests\Support\EntitesDeTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contrôle d'accès au back-office et rendu de chaque page CRUD EasyAdmin.
 */
final class ReservationCrudControllerFunctionalTest extends WebTestCase
{
    use EntitesDeTestTrait;

    public function testAdminRedirigeUnVisiteurVersLaConnexion(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testAdminEstInterditAUnUtilisateurSimple(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerUtilisateur());

        $client->request('GET', '/admin/reservation');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDashboardRedirigeVersLesUtilisateurs(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerAdmin());

        $client->request('GET', '/admin');

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/admin/user',
            $client->getResponse()->headers->get('Location')
        );
    }

    /**
     * Rend chaque page avec des données, pour détecter un champ mal configuré.
     */
    #[DataProvider('pagesAdmin')]
    public function testPageAdminSAfficheAvecUnAdministrateur(string $url): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerAdmin());

        $formateur = $this->creerFormateur();
        $formation = $this->creerFormation();
        $student = $this->creerUtilisateur('etudiant@example.test')->getStudent();
        $disponibilite = $this->creerDisponibilite($formateur, $formation);
        static::getContainer()->get(ReservationService::class)
            ->creerReservations($student, $disponibilite->getId(), 'lundi');

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    public static function pagesAdmin(): iterable
    {
        $entites = ['user', 'student', 'formateur', 'formation', 'specialite',
            'disponibilite', 'reservation', 'paiement'];

        foreach ($entites as $entite) {
            yield "$entite index" => ["/admin/$entite"];
            yield "$entite new" => ["/admin/$entite/new"];
        }
    }

    private function creerAdmin(): User
    {
        return $this->creerUtilisateur(
            'admin@example.test',
            ['ROLE_ADMIN'],
            avecProfilEtudiant: false
        );
    }
}
