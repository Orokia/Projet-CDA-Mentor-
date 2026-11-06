<?php

namespace App\Tests\Support\Paiement\Omnipay;

use Omnipay\Common\Message\AbstractResponse;

/**
 * Réponse de la passerelle Mock.
 *
 * @property array{succes: bool, message: string, reference: ?string} $data
 */
final class MockResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->data['succes'];
    }

    public function getMessage(): string
    {
        return $this->data['message'];
    }

    public function getTransactionReference(): ?string
    {
        return $this->data['reference'];
    }
}
