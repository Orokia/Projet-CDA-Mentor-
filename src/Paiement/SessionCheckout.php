<?php

namespace App\Paiement;

/**
 * Session de paiement créée par la passerelle.
 */
final readonly class SessionCheckout
{
    /**
     * @param string $id  Identifiant de la session (cs_…)
     * @param string $url Page de paiement hébergée vers laquelle rediriger l'étudiant
     */
    public function __construct(
        public string $id,
        public string $url,
    ) {
    }
}
