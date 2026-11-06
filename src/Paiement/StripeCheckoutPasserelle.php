<?php

namespace App\Paiement;

use Stripe\Checkout\Session;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Paiement par Stripe Checkout : formulaire bancaire et page de paiement hébergés par Stripe.
 *
 * - STRIPE_SECRET_KEY : clé secrète du mode test (sk_test_…) en dev et staging ;
 * - STRIPE_API_BASE : URL de l'API Stripe (https://api.stripe.com par défaut).
 */
final class StripeCheckoutPasserelle implements PasserellePaiementInterface
{
    // Créé à la première utilisation (une clé vide ne bloque pas le démarrage de l'application)
    private ?StripeClient $client = null;

    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $cleSecrete,
        #[Autowire('%env(STRIPE_API_BASE)%')]
        private readonly string $urlApi,
    ) {
    }

    public function creerSession(DemandeCheckout $demande): SessionCheckout
    {
        $metadonnees = ['paiement_id' => $demande->referenceInterne];

        $parametres = [
            'mode' => 'payment',
            'locale' => 'fr',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $demande->devise,
                    'unit_amount' => $demande->montantCentimes,
                    'product_data' => [
                        'name' => $demande->libelle,
                        'description' => $demande->description,
                    ],
                ],
            ]],
            'client_reference_id' => $demande->referenceInterne,
            'metadata' => $metadonnees,
            'payment_intent_data' => ['metadata' => $metadonnees],
            'success_url' => $demande->urlSucces,
            'cancel_url' => $demande->urlAnnulation,
            'expires_at' => $demande->expireA->getTimestamp(),
        ];

        if (null !== $demande->emailClient && '' !== $demande->emailClient) {
            $parametres['customer_email'] = $demande->emailClient;
        }

        // Clé d'idempotence : un nouvel essai réseau ne crée pas une seconde session
        $session = $this->client()->checkout->sessions->create($parametres, [
            'idempotency_key' => 'mentor-checkout-'.$demande->referenceInterne,
        ]);

        if (!\is_string($session->url) || '' === $session->url) {
            throw new \UnexpectedValueException(sprintf('Stripe n\'a pas renvoyé d\'URL de paiement pour la session %s.', $session->id));
        }

        return new SessionCheckout($session->id, $session->url);
    }

    public function recupererSession(string $sessionId): EtatSessionCheckout
    {
        $session = $this->client()->checkout->sessions->retrieve($sessionId);

        // payment_intent : identifiant, ou objet si la réponse a été « expand »
        $paymentIntent = $session->payment_intent;
        $referencePaiement = \is_string($paymentIntent) ? $paymentIntent : ($paymentIntent->id ?? null);

        return new EtatSessionCheckout(
            sessionId: $session->id,
            paye: Session::STATUS_COMPLETE === $session->status
                && Session::PAYMENT_STATUS_PAID === $session->payment_status,
            montantCentimes: $session->amount_total,
            devise: $session->currency,
            referencePaiement: $referencePaiement,
            referenceInterne: $session->client_reference_id,
        );
    }

    public function expirerSession(string $sessionId): void
    {
        try {
            $this->client()->checkout->sessions->expire($sessionId);
        } catch (InvalidRequestException $exception) {
            // Session déjà expirée ou terminée : rien à fermer
            if ('checkout_session_not_open' !== $exception->getStripeCode()
                && 'resource_missing' !== $exception->getStripeCode()) {
                throw $exception;
            }
        }
    }

    public function rembourser(string $referencePaiement): void
    {
        $this->client()->refunds->create(
            ['payment_intent' => $referencePaiement],
            ['idempotency_key' => 'mentor-remboursement-'.$referencePaiement]
        );
    }

    private function client(): StripeClient
    {
        if (null !== $this->client) {
            return $this->client;
        }

        if ('' === trim($this->cleSecrete)) {
            throw new \LogicException('Paiement Stripe non configuré : la variable STRIPE_SECRET_KEY est vide.');
        }

        return $this->client = new StripeClient([
            'api_key' => $this->cleSecrete,
            'api_base' => rtrim($this->urlApi, '/'),
            // Nouvel essai automatique sur erreur réseau, sans risque grâce aux clés d'idempotence
            'max_network_retries' => 2,
        ]);
    }
}
