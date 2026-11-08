<?php

namespace App\Tests\Support\Paiement\Omnipay;

use Omnipay\Common\Http\ClientInterface;

/**
 * Client HTTP d'Omnipay pour la passerelle Mock : elle ne fait aucun appel réseau.
 * Évite aussi la découverte automatique d'un client PSR-18 (php-http/discovery).
 */
final class ClientHttpHorsLigne implements ClientInterface
{
    public function request($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1')
    {
        throw new \LogicException(sprintf('La passerelle Mock ne fait aucun appel réseau (%s %s).', $method, $uri));
    }
}
