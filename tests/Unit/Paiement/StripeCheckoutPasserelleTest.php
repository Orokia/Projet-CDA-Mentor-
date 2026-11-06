<?php

namespace App\Tests\Unit\Paiement;

use App\Paiement\DemandeCheckout;
use App\Paiement\StripeCheckoutPasserelle;
use App\Tests\Support\Paiement\FauxClientHttpStripe;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\Exception\InvalidRequestException;

/**
 * Requêtes envoyées à l'API Stripe Checkout et lecture de ses réponses.
 */
final class StripeCheckoutPasserelleTest extends TestCase
{
    private const API = 'http://stripe.example.test';

    private FauxClientHttpStripe $http;
    private StripeCheckoutPasserelle $passerelle;

    protected function setUp(): void
    {
        $this->http = new FauxClientHttpStripe();
        ApiRequestor::setHttpClient($this->http);

        $this->passerelle = new StripeCheckoutPasserelle('sk_test_unitaire', self::API.'/');
    }

    protected function tearDown(): void
    {
        // Le client HTTP du SDK est statique : ne pas le laisser aux autres tests
        ApiRequestor::setHttpClient(null);
    }

    private function demande(): DemandeCheckout
    {
        return new DemandeCheckout(
            referenceInterne: '42',
            libelle: 'Symfony',
            description: 'Séances du lundi avec Aminata Diallo, de 09:00 à 10:00',
            montantCentimes: 19999,
            devise: 'eur',
            emailClient: 'amadou@example.test',
            urlSucces: 'https://mentor.test/api/stripe/webhook/paiement/42/succes',
            urlAnnulation: 'https://mentor.test/api/stripe/webhook/paiement/42/annule',
            expireA: new \DateTimeImmutable('@1793000000'),
        );
    }

    /**
     * @param array<string, mixed> $valeurs
     *
     * @return array<string, mixed>
     */
    private static function session(array $valeurs = []): array
    {
        return $valeurs + [
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            'status' => 'open',
            'payment_status' => 'unpaid',
            'amount_total' => 19999,
            'currency' => 'eur',
            'client_reference_id' => '42',
            'payment_intent' => null,
        ];
    }

    public function testCreerSessionEnvoieUnPaiementCheckoutComplet(): void
    {
        $this->http->repondre(200, self::session());

        $session = $this->passerelle->creerSession($this->demande());

        self::assertSame('cs_test_123', $session->id);
        self::assertSame('https://checkout.stripe.com/c/pay/cs_test_123', $session->url);

        $requete = $this->http->derniereRequete();
        self::assertSame('POST', $requete['methode']);
        // STRIPE_API_BASE est respectée
        self::assertSame(self::API.'/v1/checkout/sessions', $requete['url']);
        self::assertContains('Idempotency-Key: mentor-checkout-42', $requete['entetes']);
        self::assertContains('Authorization: Bearer sk_test_unitaire', $requete['entetes']);

        $parametres = $requete['parametres'];
        self::assertSame('payment', $parametres['mode']);
        self::assertSame('fr', $parametres['locale']);
        self::assertSame(19999, $parametres['line_items'][0]['price_data']['unit_amount']);
        self::assertSame('eur', $parametres['line_items'][0]['price_data']['currency']);
        self::assertSame('Symfony', $parametres['line_items'][0]['price_data']['product_data']['name']);
        self::assertSame(1, $parametres['line_items'][0]['quantity']);
        self::assertSame('42', $parametres['client_reference_id']);
        self::assertSame(['paiement_id' => '42'], $parametres['metadata']);
        self::assertSame(['metadata' => ['paiement_id' => '42']], $parametres['payment_intent_data']);
        self::assertSame('amadou@example.test', $parametres['customer_email']);
        self::assertSame('https://mentor.test/api/stripe/webhook/paiement/42/succes', $parametres['success_url']);
        self::assertSame('https://mentor.test/api/stripe/webhook/paiement/42/annule', $parametres['cancel_url']);
        self::assertSame(1793000000, $parametres['expires_at']);
    }

    public function testCreerSessionSansUrlDePaiementEchoue(): void
    {
        $this->http->repondre(200, self::session(['url' => null]));

        $this->expectException(\UnexpectedValueException::class);

        $this->passerelle->creerSession($this->demande());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool, ?string}>
     */
    public static function sessionsRelues(): iterable
    {
        yield 'payée' => [['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_123'], true, 'pi_123'];
        yield 'payée, payment_intent développé' => [['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => ['id' => 'pi_456', 'object' => 'payment_intent']], true, 'pi_456'];
        yield 'page ouverte, non payée' => [['status' => 'open', 'payment_status' => 'unpaid'], false, null];
        yield 'terminée sans paiement' => [['status' => 'complete', 'payment_status' => 'unpaid'], false, null];
        yield 'expirée' => [['status' => 'expired', 'payment_status' => 'unpaid'], false, null];
    }

    /**
     * @param array<string, mixed> $valeurs
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sessionsRelues')]
    public function testRecupererSessionNeConsidereQueLesSessionsPayees(array $valeurs, bool $paye, ?string $reference): void
    {
        $this->http->repondre(200, self::session($valeurs));

        $etat = $this->passerelle->recupererSession('cs_test_123');

        self::assertSame('GET', $this->http->derniereRequete()['methode']);
        self::assertSame(self::API.'/v1/checkout/sessions/cs_test_123', $this->http->derniereRequete()['url']);
        self::assertSame($paye, $etat->paye);
        self::assertSame($reference, $etat->referencePaiement);
        self::assertSame(19999, $etat->montantCentimes);
        self::assertSame('eur', $etat->devise);
        self::assertSame('42', $etat->referenceInterne);
    }

    public function testExpirerSessionFermeLaSession(): void
    {
        $this->http->repondre(200, self::session(['status' => 'expired']));

        $this->passerelle->expirerSession('cs_test_123');

        self::assertSame('POST', $this->http->derniereRequete()['methode']);
        self::assertSame(self::API.'/v1/checkout/sessions/cs_test_123/expire', $this->http->derniereRequete()['url']);
    }

    public function testExpirerUneSessionDejaTermineeEstSansEffet(): void
    {
        $this->http->repondre(400, ['error' => [
            'type' => 'invalid_request_error',
            'code' => 'checkout_session_not_open',
            'message' => 'Only Checkout Sessions with a status in ["open"] can be expired.',
        ]]);

        $this->passerelle->expirerSession('cs_test_123');

        self::assertCount(1, $this->http->requetes);
    }

    public function testExpirerSessionPropageLesAutresErreurs(): void
    {
        $this->http->repondre(400, ['error' => [
            'type' => 'invalid_request_error',
            'code' => 'parameter_invalid_string',
            'message' => 'Invalid string.',
        ]]);

        $this->expectException(InvalidRequestException::class);

        $this->passerelle->expirerSession('cs_test_123');
    }

    public function testRembourserRembourseLePaiementUneSeuleFois(): void
    {
        $this->http->repondre(200, ['id' => 're_123', 'object' => 'refund', 'status' => 'succeeded']);

        $this->passerelle->rembourser('pi_123');

        $requete = $this->http->derniereRequete();
        self::assertSame(self::API.'/v1/refunds', $requete['url']);
        self::assertSame(['payment_intent' => 'pi_123'], $requete['parametres']);
        self::assertContains('Idempotency-Key: mentor-remboursement-pi_123', $requete['entetes']);
    }

    public function testUneCleSecreteVideDonneUnMessageClair(): void
    {
        $passerelle = new StripeCheckoutPasserelle('', self::API);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Paiement Stripe non configuré : la variable STRIPE_SECRET_KEY est vide.');

        $passerelle->recupererSession('cs_test_123');
    }
}
