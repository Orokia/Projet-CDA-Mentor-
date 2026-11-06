<?php

namespace App\Controller;

use App\Entity\Student;
use App\Entity\User;
use App\Service\PaiementService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Retours de Stripe Checkout (URL success_url et cancel_url de la session).
 */
final class PaiementController extends AbstractController
{
    public function __construct(
        private PaiementService $paiementService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Après paiement : vérification auprès de Stripe, puis création des réservations.
     * La page peut être rechargée sans risque : le paiement n'est confirmé qu'une fois.
     */
    #[Route('/api/stripe/webhook/paiement/{id}/succes', name: 'app_paiement_succes', requirements: ['id' => '\d{1,18}'], methods: ['GET'])]
    public function succes(int $id): Response
    {
        $student = $this->etudiantConnecte();

        try {
            $paiement = $this->paiementService->confirmerPaiement($student, $id);
        } catch (\InvalidArgumentException $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_formateurs');
        } catch (\Throwable $exception) {
            $this->logger->error('Échec technique à la vérification d\'un paiement.', [
                'exception' => $exception,
                'paiement' => $id,
            ]);

            // Le paiement reste « en attente » : un nouvel essai le vérifiera à nouveau
            return $this->render('paiement/verification.html.twig', [
                'paiementId' => $id,
            ], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
        }

        $this->addFlash('success', sprintf(
            'Paiement confirmé : votre réservation est enregistrée. Vous êtes inscrit à %d séance(s) chaque %s.',
            $paiement->getReservations()->count(),
            ucfirst((string) $paiement->getJour())
        ));

        return $this->redirectToRoute('app_mes_reservations');
    }

    /**
     * Retour par le lien « annuler » de la page Stripe.
     */
    #[Route('/api/stripe/webhook/paiement/{id}/annule', name: 'app_paiement_annule', requirements: ['id' => '\d{1,18}'], methods: ['GET'])]
    public function annule(int $id): Response
    {
        $student = $this->etudiantConnecte();

        try {
            $paiement = $this->paiementService->annulerPaiement($student, $id);
        } catch (\InvalidArgumentException $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_formateurs');
        } catch (\Throwable $exception) {
            $this->logger->error('Échec technique à l\'annulation d\'un paiement.', [
                'exception' => $exception,
                'paiement' => $id,
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Si vous avez été débité, votre réservation sera confirmée en rechargeant la page de retour de Stripe.');

            return $this->redirectToRoute('app_formateurs');
        }

        if ($paiement->estPaye()) {
            // Payé malgré tout (autre onglet) : la réservation a été confirmée
            $this->addFlash('success', 'Votre paiement avait déjà été effectué : votre réservation est confirmée.');

            return $this->redirectToRoute('app_mes_reservations');
        }

        $this->addFlash('info', 'Paiement annulé. Aucune réservation n\'a été créée et aucun montant n\'a été débité.');

        $formateur = $paiement->getDisponibilite()?->getFormateur();

        return null !== $formateur
            ? $this->redirectToRoute('app_formateur_profil', ['id' => $formateur->getId()])
            : $this->redirectToRoute('app_formateurs');
    }

    private function etudiantConnecte(): Student
    {
        $user = $this->getUser();
        $student = $user instanceof User ? $user->getStudent() : null;

        if (null === $student) {
            throw $this->createNotFoundException('Aucun profil étudiant associé à votre compte.');
        }

        return $student;
    }
}
