<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Disponibilite;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\User;
use App\Service\PaiementService;
use App\Tests\Support\EntitesDeTestTrait;
use App\Tests\Support\Paiement\PasserellePaiementMock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Webhook Stripe : l'étudiant paie avec la carte 4242 4242 4242 4242 puis ferme
 * l'onglet ; Stripe prévient Mentor, qui confirme quand même la réservation.
 */
final class StripeWebhookControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private const URL = '/api/stripe/webhook';
    // Même valeur que STRIPE_WEBHOOK_SECRET dans .env.test et .env.ci
    private const SECRET = 'whsec_test_mentor';

    private KernelBrowser $client;
    private PasserellePaiementMock $passerelle;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        PasserellePaiementMock::reinitialiser();
        $this->passerelle = static::getContainer()->get(PasserellePaiementMock::class);
    }

    /**
     * Paiement démarré (étudiant envoyé sur Stripe), sans retour du navigateur.
     *
     * @return array{0: User, 1: Disponibilite, 2: string} étudiant, créneau, session Stripe
     */
    private function demarrerPaiement(): array
    {
        $user = $this->creerUtilisateur();
        $disponibilite = $this->creerDisponibilite($this->creerFormateur(), $this->creerFormation());

        $url = static::getContainer()->get(PaiementService::class)
            ->demarrerPaiement($user->getStudent(), $disponibilite->getId(), 'lundi');

        return [$user, $disponibilite, PasserellePaiementMock::idDepuisUrl($url)];
    }

    private function envoyer(string $type, string $sessionId, ?string $signature = null, string $objet = 'checkout.session'): void
    {
        $contenu = json_encode([
            'id' => 'evt_'.bin2hex(random_bytes(6)),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => ['id' => $sessionId, 'object' => $objet]],
        ], \JSON_THROW_ON_ERROR);

        if (null === $signature) {
            $horodatage = time();
            $signature = sprintf('t=%d,v1=%s', $horodatage, hash_hmac('sha256', $horodatage.'.'.$contenu, self::SECRET));
        }

        $this->client->request('POST', self::URL, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], content: $contenu);
    }

    private function paiement(string $sessionId): Paiement
    {
        $this->em()->clear();

        return $this->em()->getRepository(Paiement::class)->findOneBy(['sessionCheckoutId' => $sessionId]);
    }

    private function nombreDeReservations(): int
    {
        return $this->em()->getRepository(Reservation::class)->count([]);
    }

    public function testConfirmeLaReservationQuandLEtudiantAFermeLOnglet(): void
    {
        [, $disponibilite, $sessionId] = $this->demarrerPaiement();
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte('4242 4242 4242 4242', cvc: '123'));

        // Aucun retour du navigateur : seul Stripe appelle Mentor, sans session utilisateur
        $this->envoyer('checkout.session.completed', $sessionId);

        self::assertResponseIsSuccessful();

        $paiement = $this->paiement($sessionId);
        self::assertSame(Paiement::STATUT_PAYE, $paiement->getStatut());
        self::assertNotNull($paiement->getDatePaiement());

        // Deux lundis dans la période : deux séances confirmées, avec leur lien Jitsi
        $reservations = $paiement->getReservations();
        self::assertCount(2, $reservations);

        foreach ($reservations as $reservation) {
            self::assertSame(Reservation::STATUT_CONFIRMEE, $reservation->getStatut());
            self::assertStringStartsWith('https://meet.jit.si/Mentor-Reservation-', (string) $reservation->getLienVisio());
        }

        $jours = $this->em()->find(Disponibilite::class, $disponibilite->getId())->getJoursSemaine();
        self::assertSame(Disponibilite::STATUT_PLUS_DISPONIBLE, $jours['lundi']);
    }

    public function testEvenementRenvoyeParStripeNeCreePasDeDoublon(): void
    {
        [, , $sessionId] = $this->demarrerPaiement();
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());

        $this->envoyer('checkout.session.completed', $sessionId);
        $this->envoyer('checkout.session.completed', $sessionId);

        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->nombreDeReservations());
    }

    public function testRetourDuNavigateurApresLeWebhookNeCreePasDeDoublon(): void
    {
        [$user, , $sessionId] = $this->demarrerPaiement();
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());

        $this->envoyer('checkout.session.completed', $sessionId);
        self::assertSame(2, $this->nombreDeReservations());

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/stripe/webhook/paiement/'.$this->paiement($sessionId)->getId().'/succes');

        self::assertResponseRedirects('/mes-reservations');
        self::assertSame(2, $this->nombreDeReservations());
    }

    public function testWebhookApresLeRetourDuNavigateurNeCreePasDeDoublon(): void
    {
        [$user, , $sessionId] = $this->demarrerPaiement();
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/stripe/webhook/paiement/'.$this->paiement($sessionId)->getId().'/succes');
        self::assertSame(2, $this->nombreDeReservations());

        $this->envoyer('checkout.session.completed', $sessionId);

        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->nombreDeReservations());
    }

    public function testRefuseUneSignatureInvalide(): void
    {
        [, , $sessionId] = $this->demarrerPaiement();
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());

        $horodatage = time();
        $this->envoyer('checkout.session.completed', $sessionId, sprintf('t=%d,v1=%s', $horodatage, str_repeat('0', 64)));

        self::assertResponseStatusCodeSame(400);
        self::assertSame(Paiement::STATUT_EN_ATTENTE, $this->paiement($sessionId)->getStatut());
        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testRefuseUnAppelSansSignature(): void
    {
        $this->client->request('POST', self::URL, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(400);
    }

    public function testNAccepteQueLaMethodePost(): void
    {
        $this->client->request('GET', self::URL);

        self::assertResponseStatusCodeSame(405);
    }

    /**
     * Le contenu de l'événement ne suffit pas : le paiement est relu auprès de Stripe.
     */
    public function testNeConfirmePasUneSessionNonPayee(): void
    {
        [, , $sessionId] = $this->demarrerPaiement();

        $this->envoyer('checkout.session.completed', $sessionId);

        self::assertResponseIsSuccessful();
        self::assertSame(Paiement::STATUT_EN_ATTENTE, $this->paiement($sessionId)->getStatut());
        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testRembourseSiLeJourAEtePrisPendantLePaiement(): void
    {
        [, $disponibilite, $sessionId] = $this->demarrerPaiement();
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());

        // Pendant le paiement, le lundi est réservé par ailleurs (administration)
        $jours = $disponibilite->getJoursSemaine();
        $jours['lundi'] = Disponibilite::STATUT_PLUS_DISPONIBLE;
        $disponibilite->setJoursSemaine($jours);
        $this->em()->flush();

        $this->envoyer('checkout.session.completed', $sessionId);

        // Cas définitif : 200, Stripe ne renverra pas l'événement
        self::assertResponseIsSuccessful();
        self::assertSame(Paiement::STATUT_REMBOURSE, $this->paiement($sessionId)->getStatut());
        self::assertCount(1, PasserellePaiementMock::remboursements());
        self::assertSame(0, $this->nombreDeReservations());
    }

    /**
     * Erreur technique (Stripe injoignable) : 500, pour que Stripe renvoie l'événement plus tard.
     */
    public function testErreurTechniqueDemandeUnNouvelEnvoi(): void
    {
        [, , $sessionId] = $this->demarrerPaiement();
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());
        PasserellePaiementMock::simulerPanne();

        $this->envoyer('checkout.session.completed', $sessionId);

        self::assertResponseStatusCodeSame(500);
        self::assertSame(Paiement::STATUT_EN_ATTENTE, $this->paiement($sessionId)->getStatut());
        self::assertSame(0, $this->nombreDeReservations());

        // Nouvel envoi par Stripe une fois le service rétabli
        PasserellePaiementMock::simulerPanne(false);
        $this->envoyer('checkout.session.completed', $sessionId);

        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->nombreDeReservations());
    }

    public function testSessionExpireeAnnuleLePaiementEnAttente(): void
    {
        [, , $sessionId] = $this->demarrerPaiement();

        $this->envoyer('checkout.session.expired', $sessionId);

        self::assertResponseIsSuccessful();
        self::assertSame(Paiement::STATUT_ANNULE, $this->paiement($sessionId)->getStatut());
    }

    public function testSessionExpireeNeModifiePasUnPaiementDejaPaye(): void
    {
        [, , $sessionId] = $this->demarrerPaiement();
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());
        $this->envoyer('checkout.session.completed', $sessionId);

        $this->envoyer('checkout.session.expired', $sessionId);

        self::assertResponseIsSuccessful();
        self::assertSame(Paiement::STATUT_PAYE, $this->paiement($sessionId)->getStatut());
    }

    public function testIgnoreUneSessionInconnue(): void
    {
        $this->envoyer('checkout.session.completed', 'cs_test_inconnue');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testIgnoreLesAutresEvenements(): void
    {
        [, , $sessionId] = $this->demarrerPaiement();
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());

        $this->envoyer('checkout.session.async_payment_failed', $sessionId);
        $this->envoyer('payment_intent.succeeded', 'pi_test_123', objet: 'payment_intent');

        self::assertResponseIsSuccessful();
        self::assertSame(Paiement::STATUT_EN_ATTENTE, $this->paiement($sessionId)->getStatut());
        self::assertSame(0, $this->nombreDeReservations());
    }
}
