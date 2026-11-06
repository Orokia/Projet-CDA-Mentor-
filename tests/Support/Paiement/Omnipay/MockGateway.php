<?php

namespace App\Tests\Support\Paiement\Omnipay;

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\RequestInterface;

/**
 * Passerelle Omnipay « Mock » pour les tests : aucun appel réseau.
 *
 * Reproduit le comportement des cartes de test Stripe :
 * - 4242 4242 4242 4242 (date future, CVC 123) : paiement accepté ;
 * - 4000 0000 0000 0002 : carte refusée ;
 * - 4000 0000 0000 9995 : fonds insuffisants.
 * Le numéro (Luhn), la date d'expiration et le CVC sont validés par Omnipay.
 */
final class MockGateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'Mock';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return ['testMode' => true];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function purchase(array $parameters = []): RequestInterface
    {
        return $this->createRequest(MockPurchaseRequest::class, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function refund(array $parameters = []): RequestInterface
    {
        return $this->createRequest(MockRefundRequest::class, $parameters);
    }
}
