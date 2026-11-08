<?php

namespace App\Tests\Support\Paiement;

use App\Paiement\DemandeCheckout;
use App\Paiement\EtatSessionCheckout;
use App\Paiement\Montant;
use App\Paiement\PasserellePaiementInterface;
use App\Paiement\SessionCheckout;
use App\Tests\Support\Paiement\Omnipay\ClientHttpHorsLigne;
use App\Tests\Support\Paiement\Omnipay\MockGateway;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Remplace Stripe Checkout dans les tests (environnements test et ci).
 *
 * - creerSession() : comme Stripe, renvoie l'URL d'une page de paiement hébergée ;
 * - payer() : simule la saisie de la carte sur cette page, débitée par la passerelle Omnipay Mock ;
 * - recupererSession() : statut « payé » une fois la carte acceptée.
 *
 * L'état est statique : il survit au redémarrage du noyau entre deux requêtes
 * d'un test fonctionnel (redirection vers Stripe, paiement, retour). À remettre
 * à zéro avec reinitialiser() au début de chaque test.
 */
final class PasserellePaiementMock implements PasserellePaiementInterface
{
    public const URL_PAGE_PAIEMENT = 'https://checkout.stripe.test/c/pay/';

    /**
     * @var array<string, array{demande: DemandeCheckout, paye: bool, expiree: bool, referencePaiement: ?string}>
     */
    private static array $sessions = [];

    /** @var list<string> Références des paiements remboursés */
    private static array $remboursements = [];

    private static bool $panne = false;

    private readonly MockGateway $gateway;

    public function __construct()
    {
        $this->gateway = new MockGateway(new ClientHttpHorsLigne(), new Request());
        $this->gateway->initialize();
    }

    public static function reinitialiser(): void
    {
        self::$sessions = [];
        self::$remboursements = [];
        self::$panne = false;
    }

    /**
     * Simule un prestataire injoignable : chaque appel lève une erreur technique.
     */
    public static function simulerPanne(bool $panne = true): void
    {
        self::$panne = $panne;
    }

    /**
     * Carte saisie sur la page de paiement, au format affiché par Stripe.
     */
    public static function carte(string $numero = '4242 4242 4242 4242', ?string $expiration = null, string $cvc = '123'): CreditCard
    {
        // Une date future par défaut : mois courant, dans trois ans
        $expiration ??= (new \DateTimeImmutable('+3 years'))->format('m/y');
        [$mois, $annee] = explode('/', $expiration);

        return new CreditCard([
            'number' => $numero,
            'expiryMonth' => (int) $mois,
            'expiryYear' => 2000 + (int) $annee,
            'cvv' => $cvc,
        ]);
    }

    public function creerSession(DemandeCheckout $demande): SessionCheckout
    {
        $this->verifierDisponibilite();

        $id = 'cs_test_'.bin2hex(random_bytes(12));
        self::$sessions[$id] = [
            'demande' => $demande,
            'paye' => false,
            'expiree' => false,
            'referencePaiement' => null,
        ];

        return new SessionCheckout($id, self::URL_PAGE_PAIEMENT.$id);
    }

    /**
     * Saisie de la carte sur la page de paiement : débit par la passerelle Omnipay Mock.
     * Une carte invalide (Luhn, date dépassée, CVC) lève InvalidCreditCardException.
     */
    public function payer(string $sessionId, CreditCard $carte): ResponseInterface
    {
        $session = self::$sessions[$sessionId] ?? throw new \InvalidArgumentException(sprintf('Session de paiement inconnue : %s.', $sessionId));

        if ($session['expiree']) {
            throw new \LogicException('Cette session de paiement a expiré.');
        }

        $reponse = $this->gateway->purchase([
            'amount' => Montant::depuisCentimes($session['demande']->montantCentimes),
            'currency' => mb_strtoupper($session['demande']->devise),
            'transactionId' => $session['demande']->referenceInterne,
            'card' => $carte,
        ])->send();

        if ($reponse->isSuccessful()) {
            self::$sessions[$sessionId]['paye'] = true;
            self::$sessions[$sessionId]['referencePaiement'] = $reponse->getTransactionReference();
        }

        return $reponse;
    }

    public function recupererSession(string $sessionId): EtatSessionCheckout
    {
        $this->verifierDisponibilite();

        $session = self::$sessions[$sessionId] ?? throw new \LogicException(sprintf('Session de paiement inconnue : %s.', $sessionId));

        return new EtatSessionCheckout(
            sessionId: $sessionId,
            paye: $session['paye'],
            montantCentimes: $session['demande']->montantCentimes,
            devise: $session['demande']->devise,
            referencePaiement: $session['referencePaiement'],
            referenceInterne: $session['demande']->referenceInterne,
        );
    }

    public function expirerSession(string $sessionId): void
    {
        $this->verifierDisponibilite();

        if (isset(self::$sessions[$sessionId]) && !self::$sessions[$sessionId]['paye']) {
            self::$sessions[$sessionId]['expiree'] = true;
        }
    }

    public function rembourser(string $referencePaiement): void
    {
        $this->verifierDisponibilite();

        $reponse = $this->gateway->refund(['transactionReference' => $referencePaiement])->send();

        if (!$reponse->isSuccessful()) {
            throw new \RuntimeException($reponse->getMessage() ?? 'Remboursement refusé.');
        }

        self::$remboursements[] = $referencePaiement;
    }

    public static function idDepuisUrl(string $url): string
    {
        if (!str_starts_with($url, self::URL_PAGE_PAIEMENT)) {
            throw new \InvalidArgumentException(sprintf('URL de paiement inattendue : %s.', $url));
        }

        return substr($url, \strlen(self::URL_PAGE_PAIEMENT));
    }

    public static function demande(string $sessionId): DemandeCheckout
    {
        return self::$sessions[$sessionId]['demande'] ?? throw new \InvalidArgumentException(sprintf('Session de paiement inconnue : %s.', $sessionId));
    }

    public static function estExpiree(string $sessionId): bool
    {
        return self::$sessions[$sessionId]['expiree'] ?? false;
    }

    /**
     * @return list<string>
     */
    public static function remboursements(): array
    {
        return self::$remboursements;
    }

    private function verifierDisponibilite(): void
    {
        if (self::$panne) {
            throw new \Exception('Stripe injoignable (panne simulée).');
        }
    }
}
