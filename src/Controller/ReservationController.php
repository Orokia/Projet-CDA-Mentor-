<?php

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\Student;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ReservationController extends AbstractController
{
    #[Route(
        '/reservation/{id}/{date}',
        name: 'app_reservation',
        methods: ['GET', 'POST']
    )]
    public function reservation(
        Disponibilite $disponibilite,
        string $date,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {

        /*
         * 1. Vérifier que l'utilisateur est connecté
         */

        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }


        /*
         * 2. Récupérer le profil étudiant
         */

        $student = $user->getStudent();

        if (!$student) {
            throw $this->createAccessDeniedException(
                'Vous devez avoir un profil étudiant pour effectuer une réservation.'
            );
        }


        /*
         * 3. Récupérer la formation et le formateur
         * depuis la disponibilité sélectionnée.
         */

        $formation = $disponibilite->getFormation();

        $formateur = $disponibilite->getFormateur();

        if (!$formation || !$formateur) {
            throw $this->createNotFoundException(
                'La disponibilité ne possède pas de formation ou de formateur.'
            );
        }


        /*
         * 4. Transformer la date reçue dans l'URL
         * en objet DateTime.
         *
         * Exemple :
         * 2026-09-15
         */

        $dateSession = \DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

        if (!$dateSession) {
            throw $this->createNotFoundException(
                'La date sélectionnée est invalide.'
            );
        }


        /*
         * 5. Vérifier que la date est comprise
         * entre dateDebut et dateFin.
         */

        $dateDebut = $disponibilite->getDateDebut();
        $dateFin = $disponibilite->getDateFin();

        if (
            !$dateDebut ||
            !$dateFin ||
            $dateSession < $dateDebut ||
            $dateSession > $dateFin
        ) {
            throw $this->createAccessDeniedException(
                'Cette date ne fait pas partie de la période de disponibilité.'
            );
        }


        /*
         * 6. Vérifier le jour de la semaine.
         *
         * PHP :
         * 1 = lundi
         * 2 = mardi
         * ...
         * 7 = dimanche
         */

        $jours = [
            1 => 'lundi',
            2 => 'mardi',
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
            7 => 'dimanche',
        ];

        $numeroJour = (int) $dateSession->format('N');

        $jourSelectionne = $jours[$numeroJour];

        if (
            !in_array(
                $jourSelectionne,
                $disponibilite->getJoursSemaine(),
                true
            )
        ) {
            throw $this->createAccessDeniedException(
                'Le formateur n\'est pas disponible ce jour-là.'
            );
        }


        /*
         * 7. Construire la date et heure de début.
         */

        $heureDebut = $disponibilite->getHeureDebut();

        if (!$heureDebut) {
            throw new \LogicException(
                'L\'heure de début de la disponibilité est obligatoire.'
            );
        }

        $dateHeureDebut = new \DateTime(
            $dateSession->format('Y-m-d')
            . ' '
            . $heureDebut->format('H:i:s')
        );


        /*
         * 8. Calculer automatiquement l'heure de fin
         * à partir de la durée de la formation.
         */

        $dateHeureFin = clone $dateHeureDebut;

        $dateHeureFin->modify(
            '+' . $formation->getDuree() . ' minutes'
        );


        /*
         * 9. Vérifier que la session ne dépasse pas
         * la fin de la disponibilité.
         */

        $heureFinDisponibilite = $disponibilite->getHeureFin();

        if ($heureFinDisponibilite) {

            $dateHeureFinDisponibilite = new \DateTime(
                $dateSession->format('Y-m-d')
                . ' '
                . $heureFinDisponibilite->format('H:i:s')
            );

            if ($dateHeureFin > $dateHeureFinDisponibilite) {
                throw $this->createAccessDeniedException(
                    'La durée de la formation dépasse la disponibilité du formateur.'
                );
            }
        }


        /*
         * 10. Vérifier qu'il n'existe pas déjà
         * une réservation sur ce créneau.
         */

        $reservationExistante = $entityManager
            ->getRepository(Reservation::class)
            ->findOneBy([
                'formateur' => $formateur,
                'dateHeureDebut' => $dateHeureDebut,
                'statut' => 'CONFIRMEE',
            ]);

        if ($reservationExistante) {
            throw $this->createAccessDeniedException(
                'Ce créneau est déjà réservé.'
            );
        }


        /*
         * 11. Si le formulaire est envoyé,
         * créer la réservation.
         */

        if ($request->isMethod('POST')) {

            /*
             * Création de la réservation
             */

            $reservation = new Reservation();

            $reservation->setStudent($student);
            $reservation->setFormateur($formateur);
            $reservation->setFormation($formation);

            $reservation->setDateReservation(
                new \DateTime()
            );

            $reservation->setDateHeureDebut(
                $dateHeureDebut
            );

            $reservation->setDateHeureFin(
                $dateHeureFin
            );

            $reservation->setStatut(
                'EN_ATTENTE'
            );


            /*
             * Création du paiement
             */

            $paiement = new Paiement();

            $paiement->setMontant(
                (string) $formation->getPrix()
            );

            $paiement->setMode('stripe');

            $paiement->setStatut('EN_ATTENTE');


            /*
             * Association Reservation / Paiement
             */

            $reservation->setPaiement($paiement);

            $paiement->setReservation($reservation);


            /*
             * Sauvegarde en base
             */

            $entityManager->persist($paiement);
            $entityManager->persist($reservation);

            $entityManager->flush();


            /*
             * Configuration de Stripe
             */

            Stripe::setApiKey(
                $this->getParameter('stripe_secret_key')
            );


            /*
             * Stripe utilise les centimes.
             *
             * Exemple :
             * 80 € = 8000
             */

            $montant = (int) round(
                ((float) $formation->getPrix()) * 100
            );


            /*
             * Création de la session Stripe Checkout
             */

            $checkoutSession = Session::create([

                'mode' => 'payment',

                'line_items' => [[

                    'price_data' => [

                        'currency' => 'eur',

                        'product_data' => [

                            'name' => $formation->getTitre(),

                            'description' =>
                                'Session avec '
                                . $formateur->getPrenom()
                                . ' '
                                . $formateur->getNom(),

                        ],

                        'unit_amount' => $montant,

                    ],

                    'quantity' => 1,

                ]],


                /*
                 * URL après paiement
                 */

                'success_url' => $this->generateUrl(
                    'app_reservation_success',
                    [
                        'id' => $reservation->getId(),
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ) . '?session_id={CHECKOUT_SESSION_ID}',


                /*
                 * URL si l'utilisateur annule
                 */

                'cancel_url' => $this->generateUrl(
                    'app_reservation_cancel',
                    [
                        'id' => $reservation->getId(),
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),


                /*
                 * Informations que Stripe conserve
                 * avec la transaction.
                 */

                'metadata' => [

                    'reservation_id' =>
                        (string) $reservation->getId(),

                    'formation_id' =>
                        (string) $formation->getId(),

                    'formateur_id' =>
                        (string) $formateur->getId(),

                    'student_id' =>
                        (string) $student->getId(),

                ],
            ]);


            /*
             * Enregistrer l'ID de la session Stripe.
             */

            $paiement->setStripeSessionId(
                $checkoutSession->id
            );

            $entityManager->flush();


            /*
             * Redirection vers Stripe
             */

            return $this->redirect(
                $checkoutSession->url
            );
        }


        /*
         * 12. Afficher la page de réservation.
         */

        return $this->render(
            'reservation/reservation.html.twig',
            [

                'disponibilite' => $disponibilite,

                'formation' => $formation,

                'formateur' => $formateur,

                'student' => $student,

                'dateSession' => $dateSession,

                'dateHeureDebut' => $dateHeureDebut,

                'dateHeureFin' => $dateHeureFin,

            ]
        );
    }


    /*
     * ============================
     * PAIEMENT RÉUSSI
     * ============================
     */

    #[Route(
        '/reservation/{id}/success',
        name: 'app_reservation_success',
        methods: ['GET']
    )]
    public function success(
        Reservation $reservation
    ): Response {

        return $this->render(
            'reservation/success.html.twig',
            [
                'reservation' => $reservation,
            ]
        );
    }


    /*
     * ============================
     * PAIEMENT ANNULÉ
     * ============================
     */

    #[Route(
        '/reservation/{id}/cancel',
        name: 'app_reservation_cancel',
        methods: ['GET']
    )]
    public function cancel(
        Reservation $reservation
    ): Response {

        return $this->render(
            'reservation/cancel.html.twig',
            [
                'reservation' => $reservation,
            ]
        );
    }
}