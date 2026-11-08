<?php

namespace App\Tests\Support\Paiement;

use Stripe\HttpClient\ClientInterface;
use Stripe\Util\CaseInsensitiveArray;

/**
 * Client HTTP du SDK Stripe pour les tests unitaires : enregistre les requêtes
 * et renvoie des réponses préparées, sans appel réseau.
 */
final class FauxClientHttpStripe implements ClientInterface
{
    /** @var list<array{methode: string, url: string, entetes: list<string>, parametres: array<string, mixed>}> */
    public array $requetes = [];

    /** @var list<array{0: int, 1: array<string, mixed>}> */
    private array $reponses = [];

    /**
     * @param array<string, mixed> $corps
     */
    public function repondre(int $code, array $corps): self
    {
        $this->reponses[] = [$code, $corps];

        return $this;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requetes[] = [
            'methode' => mb_strtoupper($method),
            'url' => $absUrl,
            'entetes' => $headers,
            'parametres' => $params,
        ];

        [$code, $corps] = array_shift($this->reponses)
            ?? throw new \LogicException(sprintf('Aucune réponse préparée pour %s %s.', $method, $absUrl));

        return [json_encode($corps, \JSON_THROW_ON_ERROR), $code, new CaseInsensitiveArray(['request-id' => 'req_test'])];
    }

    public function derniereRequete(): array
    {
        return $this->requetes[array_key_last($this->requetes)] ?? throw new \LogicException('Aucune requête envoyée.');
    }
}
