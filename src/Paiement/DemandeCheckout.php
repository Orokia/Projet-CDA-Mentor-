<?php

namespace App\Paiement;

/**
 * Données envoyées à la passerelle pour créer une session de paiement.
 */
final readonly class DemandeCheckout
{
    /**
     * @param int    $montantCentimes Montant en plus petite unité de la devise (centimes)
     * @param string $devise          Code ISO 4217 en minuscules (« eur »)
     * @param string $urlSucces       URL absolue de retour après paiement
     * @param string $urlAnnulation   URL absolue de retour si l'étudiant abandonne
     */
    public function __construct(
        public string $referenceInterne,
        public string $libelle,
        public string $description,
        public int $montantCentimes,
        public string $devise,
        public ?string $emailClient,
        public string $urlSucces,
        public string $urlAnnulation,
        public \DateTimeImmutable $expireA,
    ) {
        if ($montantCentimes <= 0) {
            throw new \InvalidArgumentException('Le montant à payer doit être positif.');
        }
    }
}
