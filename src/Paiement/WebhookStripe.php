<?php

namespace App\Paiement;

use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lecture des événements envoyés par Stripe au webhook de Mentor.
 *
 * STRIPE_WEBHOOK_SECRET : secret de signature du point de terminaison (whsec_…),
 * affiché dans le tableau de bord Stripe (Développeurs → Webhooks) ou par
 * « stripe listen » en local.
 */
final class WebhookStripe
{
    // Paiement validé : immédiat (carte) ou différé (prélèvement, virement)
    public const EVENEMENTS_PAIEMENT = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
    ];

    // Session non payée avant son expiration
    public const EVENEMENT_EXPIRATION = 'checkout.session.expired';

    // Écart toléré entre l'horodatage de la signature et l'heure du serveur (rejeu)
    private const TOLERANCE_SECONDES = 300;

    public function __construct(
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $secret,
    ) {
    }

    /**
     * @throws \UnexpectedValueException signature absente ou invalide, contenu illisible
     * @throws \LogicException            secret du webhook non configuré
     */
    public function lireEvenement(string $contenu, string $signature): EvenementCheckout
    {
        if ('' === trim($this->secret)) {
            throw new \LogicException('Webhook Stripe non configuré : la variable STRIPE_WEBHOOK_SECRET est vide.');
        }

        try {
            $evenement = Webhook::constructEvent($contenu, $signature, $this->secret, self::TOLERANCE_SECONDES);
        } catch (SignatureVerificationException $exception) {
            throw new \UnexpectedValueException('Signature du webhook Stripe invalide.', 0, $exception);
        }

        $objet = $evenement->data->object ?? null;
        $sessionId = (null !== $objet && 'checkout.session' === ($objet->object ?? null)) ? $objet->id : null;

        return new EvenementCheckout(
            id: (string) $evenement->id,
            type: (string) $evenement->type,
            sessionId: \is_string($sessionId) ? $sessionId : null,
        );
    }
}
