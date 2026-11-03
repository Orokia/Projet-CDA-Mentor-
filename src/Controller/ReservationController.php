<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Service\JitsiService;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationController extends AbstractController
{
    public function __construct(
        private ReservationService $reservationService
    ) {
    }

    /**
     * Crée les réservations de l'étudiant connecté.
     */
    #[Route(
        '/reservation/create',
        name: 'app_reservation_create',
        methods: ['POST']
    )]
    public function create(Request $request): Response
    {
        // Vérification de la connexion
        $user = $this->getUser();

        if (!$user || !$user instanceof User) {
            $this->addFlash(
                'error',
                'Vous devez être connecté pour réserver une session.'
            );

            return $this->redirectToRoute('app_login');
        }

        // Vérification du profil étudiant
        $student = $user->getStudent();

        if (!$student) {
            throw $this->createNotFoundException(
                'Aucun profil étudiant associé à votre compte.'
            );
        }

        // Vérification du jeton CSRF envoyé par assets/reservation.js
        if (!$this->isCsrfTokenValid('reservation', $request->request->getString('_token'))) {
            $this->addFlash(
                'error',
                'La session a expiré. Veuillez réessayer.'
            );

            return $this->redirectToRoute('app_formateurs');
        }

        // Récupération des données du formulaire
        $disponibiliteId = $request->request->get('disponibilite');
        $jourChoisi = $request->request->get('jour');

        if (
            !$disponibiliteId ||
            !ctype_digit((string) $disponibiliteId)
        ) {
            $this->addFlash(
                'error',
                'Veuillez sélectionner une disponibilité valide.'
            );

            return $this->redirectToRoute('app_formateurs');
        }

        if (!$jourChoisi) {
            $this->addFlash(
                'error',
                'Veuillez sélectionner un jour.'
            );

            return $this->redirectToRoute('app_formateurs');
        }

        // Appel du service pour créer les réservations
        try {
            $reservations = $this->reservationService->creerReservations(
                $student,
                (int) $disponibiliteId,
                (string) $jourChoisi
            );
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_formateurs');
        }

        // Message de confirmation
        $this->addFlash(
            'success',
            sprintf(
                'Votre réservation a été créée. Vous êtes inscrit à %d séance(s) chaque %s.',
                count($reservations),
                ucfirst(mb_strtolower(trim((string) $jourChoisi)))
            )
        );

        // Redirection vers le profil du formateur réservé
        $formateur = $reservations[0]->getFormateur();

        return $this->redirectToRoute(
            'app_formateur_profil',
            ['id' => $formateur->getId()]
        );
    }

    /**
     * Affiche la salle de visioconférence d'une réservation dans la page.
     */
    #[Route(
        '/reservation/{id}/visio',
        name: 'app_reservation_visio',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    public function visio(
        Reservation $reservation,
        JitsiService $jitsiService
    ): Response {
        // Vérification de la connexion
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Seuls les participants de la séance peuvent y accéder
        if (!$jitsiService->peutRejoindre($reservation, $user)) {
            throw $this->createAccessDeniedException();
        }

        // Sans identifiants JaaS, la salle s'ouvre directement sur Jitsi
        if (!$jitsiService->estConfigure()) {
            return $this->redirect((string) $reservation->getLienVisio());
        }

        try {
            $salle = $jitsiService->preparerSalle($reservation, $user);
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_mes_reservations');
        }

        return $this->render(
            'reservation/visio.html.twig',
            ['reservation' => $reservation] + $salle
        );
    }

    /**
     * Affiche les réservations de l'étudiant ou du formateur connecté,
     * regroupées par formation.
     */
    #[Route(
        '/mes-reservations',
        name: 'app_mes_reservations',
        methods: ['GET']
    )]
    public function mesReservations(): Response
    {
        // Vérification de la connexion
        $user = $this->getUser();

        if (!$user || !$user instanceof User ) {
            return $this->redirectToRoute('app_login');
        }
       
        // Récupération du profil lié au compte
        $profil = $user->getStudent() ?? $user->getFormateur();

        if (!$profil) {
            throw $this->createNotFoundException(
                'Aucun profil étudiant ou formateur associé à votre compte.'
            );
        }

        // Récupération des réservations du profil
        $reservations = $profil->getReservations();

        // Regroupement effectué par le service
        $reservationsParFormation =
            $this->reservationService->regrouperParFormation($reservations);

        // Affichage du template Twig
        return $this->render(
            'reservation/index.html.twig',
            [
                'reservationsParFormation' => $reservationsParFormation,
            ]
        );
    }
}
