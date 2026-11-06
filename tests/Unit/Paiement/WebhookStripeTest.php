<?php

namespace App\Tests\Unit\Paiement;

use App\Paiement\WebhookStripe;
use PHPUnit\Framework\TestCase;

/**
 * Vérification de la signature des événements envoyés par Stripe.
 */
class WebhookStripeTest extends TestCase
{
    private const SECRET = 'whsec_unitaire';

    private function contenu(string $type = 'checkout.session.completed', string $objet = 'checkout.session'): string
    {
        return json_encode([
            'id' => 'evt_123',
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => ['id' => 'cs_test_abc', 'object' => $objet]],
        ], \JSON_THROW_ON_ERROR);
    }

    private function signer(string $contenu, string $secret = self::SECRET, ?int $horodatage = null): string
    {
        $horodatage ??= time();

        return sprintf('t=%d,v1=%s', $horodatage, hash_hmac('sha256', $horodatage.'.'.$contenu, $secret));
    }

    public function testLitUnEvenementCorrectementSigne(): void
    {
        $contenu = $this->contenu();

        $evenement = (new WebhookStripe(self::SECRET))->lireEvenement($contenu, $this->signer($contenu));

        self::assertSame('evt_123', $evenement->id);
        self::assertSame('checkout.session.completed', $evenement->type);
        self::assertSame('cs_test_abc', $evenement->sessionId);
    }

    public function testSessionAbsentePourUnAutreObjet(): void
    {
        $contenu = $this->contenu('payment_intent.succeeded', 'payment_intent');

        $evenement = (new WebhookStripe(self::SECRET))->lireEvenement($contenu, $this->signer($contenu));

        self::assertSame('payment_intent.succeeded', $evenement->type);
        self::assertNull($evenement->sessionId);
    }

    /**
     * @return iterable<string, array{0: callable(string): string}>
     */
    public static function signaturesInvalides(): iterable
    {
        yield 'mauvais secret' => [fn (string $c): string => sprintf('t=%d,v1=%s', time(), hash_hmac('sha256', time().'.'.$c, 'whsec_pirate'))];
        yield 'absente' => [fn (string $c): string => ''];
        yield 'mal formée' => [fn (string $c): string => 'n-importe-quoi'];
        yield 'trop ancienne (rejeu)' => [fn (string $c): string => sprintf('t=%d,v1=%s', time() - 3600, hash_hmac('sha256', (time() - 3600).'.'.$c, self::SECRET))];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('signaturesInvalides')]
    public function testRefuseUneSignatureInvalide(callable $signature): void
    {
        $contenu = $this->contenu();

        $this->expectException(\UnexpectedValueException::class);

        (new WebhookStripe(self::SECRET))->lireEvenement($contenu, $signature($contenu));
    }

    public function testRefuseUnContenuModifieApresSignature(): void
    {
        $signature = $this->signer($this->contenu());

        $this->expectException(\UnexpectedValueException::class);

        (new WebhookStripe(self::SECRET))->lireEvenement($this->contenu('checkout.session.expired'), $signature);
    }

    public function testRefuseUnContenuIllisible(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        (new WebhookStripe(self::SECRET))->lireEvenement('{pas du json', $this->signer('{pas du json'));
    }

    public function testExigeUnSecretConfigure(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('STRIPE_WEBHOOK_SECRET');

        (new WebhookStripe(' '))->lireEvenement($this->contenu(), 'signature');
    }
}
