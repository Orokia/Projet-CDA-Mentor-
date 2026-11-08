<?php

namespace App\Controller;

use App\Paiement\WebhookStripe;
use App\Service\PaiementService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Webhook Stripe : appelé par les serveurs de Stripe, sans session utilisateur.
 *
 * Confirme la réservation même si l'étudiant a payé puis fermé l'onglet avant
 * de revenir sur Mentor. L'authenticité est garantie par la signature
 * (en-tête Stripe-Signature) ; le paiement est de toute façon relu auprès de Stripe.
 *
 * Réponses : 2xx = événement traité ou ignoré ; 400 = signature invalide ;
 * 500 = erreur technique, Stripe renverra l'événement plus tard.
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private WebhookStripe $webhook,
        private PaiementService $paiementService,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function recevoir(Request $request): Response
    {
        try {
            $evenement = $this->webhook->lireEvenement(
                $request->getContent(),
                (string) $request->headers->get('Stripe-Signature', '')
            );
        } catch (\UnexpectedValueException $exception) {
            $this->logger->warning('Webhook Stripe refusé.', ['raison' => $exception->getMessage()]);

            return new JsonResponse(['erreur' => 'Signature invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (null === $evenement->sessionId) {
            return new JsonResponse(['statut' => 'ignore']);
        }

        try {
            if (\in_array($evenement->type, WebhookStripe::EVENEMENTS_PAIEMENT, true)) {
                $this->paiementService->confirmerDepuisStripe($evenement->sessionId);
            } elseif (WebhookStripe::EVENEMENT_EXPIRATION === $evenement->type) {
                $this->paiementService->expirerDepuisStripe($evenement->sessionId);
            } else {
                return new JsonResponse(['statut' => 'ignore']);
            }
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            // Cas métier définitif (non payé, créneau pris et remboursé…) : inutile que Stripe réessaie
            $this->logger->warning('Webhook Stripe traité sans réservation.', [
                'evenement' => $evenement->id,
                'session' => $evenement->sessionId,
                'raison' => $exception->getMessage(),
            ]);

            return new JsonResponse(['statut' => 'traite']);
        } catch (\Throwable $exception) {
            $this->logger->error('Échec technique du webhook Stripe : Stripe renverra l\'événement.', [
                'exception' => $exception,
                'evenement' => $evenement->id,
                'session' => $evenement->sessionId,
            ]);

            return new JsonResponse(['erreur' => 'Erreur technique.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['statut' => 'traite']);
    }
}
