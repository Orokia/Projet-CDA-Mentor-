<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Disponibilite;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Tests\Support\EntitesDeTestTrait;
use App\Tests\Support\Paiement\PasserellePaiementMock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Retours de Stripe Checkout vers Mentor : succès, annulation, erreurs.
 */
final class PaiementControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        PasserellePaiementMock::reinitialiser();
    }

    /**
     * Étudiant connecté, clic sur « Payer et réserver » pour le lundi.
     *
     * @return array{0: Disponibilite, 1: string} disponibilité et session de paiement
     */
    private function allerSurLaPageDePaiement(): array
    {
        $disponibilite = $this->creerDisponibilite($this->creerFormateur(), $this->creerFormation());
        $this->client->loginUser($this->creerUtilisateur());

        $token = $this->client
            ->request('GET', '/formateur/'.$disponibilite->getFormateur()->getId())
            ->filter('#btn-reserver')
            ->attr('data-csrf-token');

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);
        self::assertResponseStatusCodeSame(303);

        $sessionId = PasserellePaiementMock::idDepuisUrl((string) $this->client->getResponse()->headers->get('Location'));

        return [$disponibilite, $sessionId];
    }

    private function payer(string $sessionId, string $numero = '4242 4242 4242 4242'): void
    {
        static::getContainer()->get(PasserellePaiementMock::class)
            ->payer($sessionId, PasserellePaiementMock::carte($numero, cvc: '123'));
    }

    private function urlSucces(string $sessionId): string
    {
        return PasserellePaiementMock::demande($sessionId)->urlSucces;
    }

    private function nombreDeReservations(): int
    {
        return $this->em()->getRepository(Reservation::class)->count([]);
    }

    private function paiement(): Paiement
    {
        $this->em()->clear();

        return $this->em()->getRepository(Paiement::class)->findOneBy([]);
    }

    public function testLeRetourApresPaiementConfirmeLaReservation(): void
    {
        [, $sessionId] = $this->allerSurLaPageDePaiement();
        $this->payer($sessionId);

        $this->client->request('GET', $this->urlSucces($sessionId));

        self::assertResponseRedirects('/mes-reservations');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Paiement confirmé');
        self::assertSelectorCount(2, '.reservation-session');
        self::assertSame(Paiement::STATUT_PAYE, $this->paiement()->getStatut());
    }

    public function testRechargerLaPageDeRetourNeReservePasDeuxFois(): void
    {
        [, $sessionId] = $this->allerSurLaPageDePaiement();
        $this->payer($sessionId);

        $this->client->request('GET', $this->urlSucces($sessionId));
        $this->client->request('GET', $this->urlSucces($sessionId));

        self::assertResponseRedirects('/mes-reservations');
        self::assertSame(2, $this->nombreDeReservations());
    }

    public function testUneCarteRefuseeAfficheUneErreurSansReserver(): void
    {
        [, $sessionId] = $this->allerSurLaPageDePaiement();
        $this->payer($sessionId, '4000 0000 0000 0002');

        $this->client->request('GET', $this->urlSucces($sessionId));

        self::assertResponseRedirects('/formateurs');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', "Le paiement n'a pas été validé par Stripe.");
        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testLAnnulationRameneSurLeProfilDuFormateur(): void
    {
        [$disponibilite, $sessionId] = $this->allerSurLaPageDePaiement();

        $this->client->request('GET', PasserellePaiementMock::demande($sessionId)->urlAnnulation);

        self::assertResponseRedirects('/formateur/'.$disponibilite->getFormateur()->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-info', 'Paiement annulé');

        // Le jour reste réservable
        self::assertSelectorNotExists('.disponibilite-jour[data-jour="lundi"][disabled]');
        self::assertSame(Paiement::STATUT_ANNULE, $this->paiement()->getStatut());
        self::assertTrue(PasserellePaiementMock::estExpiree($sessionId));
    }

    public function testUnePanneALaVerificationProposeDeReessayer(): void
    {
        [, $sessionId] = $this->allerSurLaPageDePaiement();
        $this->payer($sessionId);

        PasserellePaiementMock::simulerPanne();
        $this->client->request('GET', $this->urlSucces($sessionId));

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('h1', 'Vérification du paiement en cours');
        self::assertSame(0, $this->nombreDeReservations());

        // Stripe de nouveau joignable : « Vérifier à nouveau » confirme la réservation
        PasserellePaiementMock::simulerPanne(false);
        $this->client->clickLink('Vérifier à nouveau');

        self::assertResponseRedirects('/mes-reservations');
        self::assertSame(2, $this->nombreDeReservations());
    }

    public function testLePaiementDUnAutreEtudiantRenvoie404(): void
    {
        [, $sessionId] = $this->allerSurLaPageDePaiement();
        $this->payer($sessionId);

        $this->client->loginUser($this->creerUtilisateur('autre.etudiant@example.test'));
        $this->client->request('GET', $this->urlSucces($sessionId));

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testUnVisiteurEstRedirigeVersLaConnexion(): void
    {
        $this->client->request('GET', '/api/stripe/webhook/paiement/1/succes');

        self::assertResponseRedirects('/login');
    }

    public function testUnCompteSansProfilEtudiantRenvoie404(): void
    {
        $this->client->loginUser($this->creerUtilisateur(avecProfilEtudiant: false));

        $this->client->request('GET', '/api/stripe/webhook/paiement/1/annule');

        self::assertResponseStatusCodeSame(404);
    }
}
