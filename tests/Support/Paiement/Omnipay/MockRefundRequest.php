<?php

namespace App\Tests\Support\Paiement\Omnipay;

use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Remboursement intégral d'un paiement de la passerelle Mock.
 */
final class MockRefundRequest extends AbstractRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('transactionReference');

        return ['paiement' => $this->getTransactionReference()];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData($data): ResponseInterface
    {
        return $this->response = new MockResponse($this, [
            'succes' => true,
            'message' => 'Remboursement effectué.',
            'reference' => 're_mock_'.bin2hex(random_bytes(8)),
            'paiement' => $data['paiement'],
        ]);
    }
}
