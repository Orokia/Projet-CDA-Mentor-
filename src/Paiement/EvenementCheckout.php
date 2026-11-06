<?php

namespace App\Paiement;

/**
 * Événement Stripe reçu par le webhook, dont la signature a été vérifiée.
 */
final readonly class EvenementCheckout
{
    public function __construct(
        // Identifiant de l'événement Stripe (evt_…)
        public string $id,
        // Type d'événement (checkout.session.completed…)
        public string $type,
        // Session Checkout concernée (cs_…), null pour les autres objets
        public ?string $sessionId,
    ) {
    }
}
