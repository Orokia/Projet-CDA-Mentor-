<?php

namespace App\Paiement;

/**
 * État d'une session de paiement, relu auprès de la passerelle.
 */
final readonly class EtatSessionCheckout
{
    /**
     * @param bool        $paye              Paiement encaissé (Stripe : payment_status = paid)
     * @param string|null $referencePaiement Identifiant du paiement (pi_…), connu une fois payé
     * @param string|null $referenceInterne  Identifiant du paiement Mentor transmis à la création
     */
    public function __construct(
        public string $sessionId,
        public bool $paye,
        public ?int $montantCentimes,
        public ?string $devise,
        public ?string $referencePaiement,
        public ?string $referenceInterne,
    ) {
    }
}
