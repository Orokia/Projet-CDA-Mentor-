<?php

namespace App\Paiement;

/**
 * Passerelle de paiement par page hébergée (Stripe Checkout).
 *
 * Implémentations :
 * - StripeCheckoutPasserelle : API Stripe (clé de mode test sk_test_… hors production) ;
 * - tests : passerelle Omnipay « Mock » (tests/Support/Paiement), sans réseau.
 */
interface PasserellePaiementInterface
{
    /**
     * Crée la session de paiement : l'étudiant est ensuite redirigé vers SessionCheckout::$url.
     */
    public function creerSession(DemandeCheckout $demande): SessionCheckout;

    /**
     * Relit la session auprès du prestataire : seule source fiable du statut payé,
     * le retour du navigateur pouvant être falsifié.
     */
    public function recupererSession(string $sessionId): EtatSessionCheckout;

    /**
     * Ferme une session non payée : elle ne pourra plus être réglée.
     */
    public function expirerSession(string $sessionId): void;

    /**
     * Rembourse intégralement un paiement (référence pi_…).
     */
    public function rembourser(string $referencePaiement): void;
}
