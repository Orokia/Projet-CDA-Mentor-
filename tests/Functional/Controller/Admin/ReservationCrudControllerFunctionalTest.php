<?php

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Reservation;
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
            ->creerReservations($student, $disponibilite->getId(), 'lundi', $this->paiementEffectue());

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

    /**
     * Un compte étudiant ou formateur n'accède à aucune page du back-office.
     */
    #[DataProvider('entitesAdmin')]
    public function testCrudEstInterditSansRoleAdmin(string $entite): void
    {
        $client = static::createClient();

        foreach (['ROLE_USER', 'ROLE_FORMATEUR'] as $index => $role) {
            $client->loginUser($this->creerUtilisateur(
                sprintf('compte%d.%s@exemple.fr', $index, $entite),
                [$role],
                avecProfilEtudiant: false
            ));

            $client->request('GET', "/admin/$entite");
            self::assertResponseStatusCodeSame(403, "$role sur /admin/$entite");

            $client->request('GET', "/admin/$entite/new");
            self::assertResponseStatusCodeSame(403, "$role sur /admin/$entite/new");
        }
    }

    public static function entitesAdmin(): iterable
    {
        foreach (['user', 'student', 'formateur', 'formation', 'specialite', 'disponibilite', 'reservation', 'paiement'] as $entite) {
            yield $entite => [$entite];
        }
    }

    /**
     * Rend le formulaire d'édition de chaque entité existante.
     */
    public function testPagesDEditionSAffichentAvecUnAdministrateur(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerAdmin());

        $formateur = $this->creerFormateur();
        $formation = $this->creerFormation('Développement Symfony avancé');
        $etudiant = $this->creerUtilisateur('fatoumata.keita@exemple.fr');
        $disponibilite = $this->creerDisponibilite($formateur, $formation);
        $reservations = static::getContainer()->get(ReservationService::class)
            ->creerReservations($etudiant->getStudent(), $disponibilite->getId(), 'lundi', $this->paiementEffectue());

        $pages = [
            'user' => $etudiant->getId(),
            'student' => $etudiant->getStudent()->getId(),
            'formateur' => $formateur->getId(),
            'formation' => $formation->getId(),
            'disponibilite' => $disponibilite->getId(),
            'reservation' => $reservations[0]->getId(),
        ];

        foreach ($pages as $entite => $id) {
            $client->request('GET', "/admin/$entite/$id/edit");

            self::assertResponseIsSuccessful("/admin/$entite/$id/edit");
            self::assertSelectorExists('form');
        }
    }

    /**
     * Back-office : une réservation ne passe à « confirmée » qu'avec un paiement effectué.
     */
    public function testAdminNePeutPasConfirmerUneReservationSansPaiement(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerAdmin());

        $reservation = $this->creerReservationEnAttente();

        $crawler = $client->request('GET', '/admin/reservation/'.$reservation->getId().'/edit');
        $formulaire = $crawler->filter('#edit-Reservation-form')->form();
        $formulaire['Reservation[statut]'] = Reservation::STATUT_CONFIRMEE;
        $client->submit($formulaire);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Une réservation ne peut être confirmée que si son paiement est effectué.');

        $this->em()->clear();
        self::assertSame(
            Reservation::STATUT_EN_ATTENTE,
            $this->em()->find(Reservation::class, $reservation->getId())->getStatut()
        );
    }

    public function testAdminConfirmeUneReservationDontLePaiementEstEffectue(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerAdmin());

        $reservation = $this->creerReservationEnAttente();
        $reservation->setPaiement($this->paiementEffectue());
        $this->em()->flush();

        $crawler = $client->request('GET', '/admin/reservation/'.$reservation->getId().'/edit');
        $formulaire = $crawler->filter('#edit-Reservation-form')->form();
        $formulaire['Reservation[statut]'] = Reservation::STATUT_CONFIRMEE;
        $client->submit($formulaire);

        self::assertResponseRedirects();

        $this->em()->clear();
        self::assertSame(
            Reservation::STATUT_CONFIRMEE,
            $this->em()->find(Reservation::class, $reservation->getId())->getStatut()
        );
    }

    private function creerReservationEnAttente(): Reservation
    {
        $formateur = $this->creerFormateur();
        $formation = $this->creerFormation();

        $reservation = (new Reservation())
            ->setDateReservation(new \DateTime())
            ->setDateHeureDebut(new \DateTime('2026-12-07 09:00'))
            ->setDateHeureFin(new \DateTime('2026-12-07 10:00'))
            ->setStatut(Reservation::STATUT_EN_ATTENTE)
            ->setStudent($this->creerUtilisateur('attente@example.test')->getStudent())
            ->setFormation($formation)
            ->setFormateur($formateur)
            ->setDisponibilite($this->creerDisponibilite($formateur, $formation));

        $this->em()->persist($reservation);
        $this->em()->flush();

        return $reservation;
    }

    public function testEditionDUneEntiteInexistanteRenvoie404(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerAdmin());

        $client->request('GET', '/admin/formation/999999/edit');

        self::assertResponseStatusCodeSame(404);
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
