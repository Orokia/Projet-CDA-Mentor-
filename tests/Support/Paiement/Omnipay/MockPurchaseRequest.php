<?php

namespace App\Tests\Support\Paiement\Omnipay;

use Omnipay\Common\Exception\InvalidCreditCardException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Paiement par carte sur la passerelle Mock.
 */
final class MockPurchaseRequest extends AbstractRequest
{
    // Cartes de test Stripe refusées, avec le message affiché par Stripe Checkout
    public const CARTES_REFUSEES = [
        '4000000000000002' => 'Votre carte a été refusée.',
        '4000000000009995' => 'Votre carte ne dispose pas de fonds suffisants.',
    ];

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('amount', 'currency', 'card');

        $carte = $this->getCard();
        // Numéro requis, algorithme de Luhn, 12 à 19 chiffres, date d'expiration non dépassée
        $carte->validate();

        if (!preg_match('/^\d{3,4}$/', (string) $carte->getCvv())) {
            throw new InvalidCreditCardException('Le cryptogramme visuel (CVC) est invalide.');
        }

        return [
            'montant' => $this->getAmountInteger(),
            'devise' => $this->getCurrency(),
            'numero' => $carte->getNumber(),
            'reference' => $this->getTransactionId(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData($data): ResponseInterface
    {
        $refus = self::CARTES_REFUSEES[$data['numero']] ?? null;

        return $this->response = new MockResponse($this, [
            'succes' => null === $refus,
            'message' => $refus ?? 'Paiement accepté.',
            'reference' => null === $refus ? 'pi_mock_'.bin2hex(random_bytes(8)) : null,
            'montant' => $data['montant'],
            'devise' => $data['devise'],
        ]);
    }
}
