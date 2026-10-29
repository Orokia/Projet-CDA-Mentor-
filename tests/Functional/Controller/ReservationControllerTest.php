<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Disponibilite;
use App\Entity\Reservation;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservationControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Connecte un étudiant et récupère le jeton CSRF
     * affiché sur la page du formateur (bouton « Réserver »).
     */
    private function preparerReservation(): array
    {
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation()
        );

        $this->client->loginUser($this->creerUtilisateur());

        $crawler = $this->client->request(
            'GET',
            '/formateur/' . $disponibilite->getFormateur()->getId()
        );
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#btn-reserver')->attr('data-csrf-token');

        return [$disponibilite, $token];
    }

    private function nombreDeReservations(): int
    {
        return $this->em()->getRepository(Reservation::class)->count([]);
    }

    public function testCreationRedirigeUnVisiteurVersLaConnexion(): void
    {
        $this->client->request('POST', '/reservation/create');

        self::assertResponseRedirects('/login');
    }

    public function testCreationReserveChaqueOccurrenceDuJour(): void
    {
        /** @var Disponibilite $disponibilite */
        [$disponibilite, $token] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        self::assertResponseRedirects('/formateur/' . $disponibilite->getFormateur()->getId());
        self::assertSame(2, $this->nombreDeReservations());

        // Les réservations apparaissent dans « Mes réservations »
        $this->client->request('GET', '/mes-reservations');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.reservation-group');
        self::assertSelectorCount(2, '.reservation-session');
        self::assertSelectorTextContains('.reservation-group-title', 'Symfony');
    }

    public function testCreationRefuseUnJetonCsrfInvalide(): void
    {
        [$disponibilite] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => 'jeton-invalide',
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        self::assertResponseRedirects('/formateurs');
        self::assertSame(0, $this->nombreDeReservations());
        $this->assertFlash('error', 'La session a expiré. Veuillez réessayer.');
    }

    /**
     * Les erreurs de saisie et les exceptions du service
     * deviennent un message flash, sans réservation créée.
     */
    public function testCreationAfficheLesErreursDeSaisie(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();
        $id = $disponibilite->getId();

        $cas = [
            [['disponibilite' => 'abc', 'jour' => 'lundi'], 'Veuillez sélectionner une disponibilité valide.'],
            [['disponibilite' => $id], 'Veuillez sélectionner un jour.'],
            [['disponibilite' => $id, 'jour' => 'mardi'], "Le jour choisi n'est pas disponible."],
            [['disponibilite' => 999999, 'jour' => 'lundi'], 'Disponibilité introuvable.'],
        ];

        foreach ($cas as [$parametres, $message]) {
            $this->client->request(
                'POST',
                '/reservation/create',
                ['_token' => $token] + $parametres
            );

            self::assertResponseRedirects('/formateurs');
            $this->assertFlash('error', $message);
        }

        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testMesReservationsSansProfilEtudiantRenvoie404(): void
    {
        $this->client->loginUser(
            $this->creerUtilisateur(avecProfilEtudiant: false)
        );

        $this->client->request('GET', '/mes-reservations');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreationEnGetRenvoie405(): void
    {
        $this->client->request('GET', '/reservation/create');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Allow', 'POST');
    }

    public function testCreationRefuseUnCreneauDejaReserve(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();
        $parametres = [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ];

        $this->client->request('POST', '/reservation/create', $parametres);
        self::assertResponseRedirects('/formateur/' . $disponibilite->getFormateur()->getId());

        // Deuxième envoi du même créneau (double clic, retour arrière)
        $this->client->request('POST', '/reservation/create', $parametres);

        self::assertResponseRedirects('/formateurs');
        $this->assertFlash('error', 'Vous avez déjà réservé ce créneau.');
        self::assertSame(2, $this->nombreDeReservations());
    }

    public function testCreationConfirmeLeNombreDeSeances(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => ' LUNDI ',
        ]);

        self::assertResponseRedirects();
        $this->assertFlash(
            'success',
            'Votre réservation a été créée. Vous êtes inscrit à 2 séance(s) chaque Lundi.'
        );
    }

    public function testCreationSansProfilEtudiantRenvoie404(): void
    {
        $this->client->loginUser(
            $this->creerUtilisateur('formateur.compte@exemple.fr', ['ROLE_FORMATEUR'], avecProfilEtudiant: false)
        );

        $this->client->request('POST', '/reservation/create', ['disponibilite' => '1', 'jour' => 'lundi']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testMesReservationsRedirigeUnVisiteurVersLaConnexion(): void
    {
        $this->client->request('GET', '/mes-reservations');

        self::assertResponseRedirects('/login');
    }

    public function testMesReservationsSansReservationAfficheUnMessage(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/mes-reservations');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(0, '.reservation-group');
        self::assertSelectorTextContains('h3', 'Aucune réservation');
    }

    public function testMesReservationsAfficheLeLienDeVisioconference(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        $crawler = $this->client->request('GET', '/mes-reservations');

        self::assertResponseIsSuccessful();
        $liens = $crawler->filter('.reservation-session a[href^="https://meet.jit.si/Mentor-Reservation-"]');
        self::assertCount(2, $liens);
    }

    private function assertFlash(string $type, string $message): void
    {
        $flashes = $this->client->getRequest()->getSession()->getFlashBag()->get($type);

        self::assertContains($message, $flashes);
    }
}
