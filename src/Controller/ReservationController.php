<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\DisponibiliteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationController extends AbstractController
{
    #[Route(
        '/reservation/create',
        name: 'app_reservation_create',
        methods: ['POST']
    )]
    public function create(
        Request $request,
        DisponibiliteRepository $disponibiliteRepository,
        EntityManagerInterface $entityManager
    ): Response {

        /*
         
         * 1. Vérification de la connexion
         
         */

        $user = $this->getUser();

        if (!$user) {
            $this->addFlash(
                'error',
                'Vous devez être connecté pour réserver une session.'
            );

            return $this->redirectToRoute('app_login');
        }


        /*
         
         * 2. Récupération de l'étudiant
         
         */

        $student = $user->getStudent();

        if (!$student) {
            throw $this->createNotFoundException(
                'Aucun profil étudiant associé à votre compte.'
            );
        }


        /*
         
         * 3. Récupération des données envoyées
         
         */

        $disponibiliteId = $request->request->get('disponibilite');
        $jourChoisi = $request->request->get('jour');

        if (!$disponibiliteId) {
            throw $this->createNotFoundException(
                'Aucune disponibilité sélectionnée.'
            );
        }

        if (!$jourChoisi) {
            throw $this->createNotFoundException(
                'Aucun jour sélectionné.'
            );
        }


        /*
         
         * 4. Récupération de la disponibilité
         
         */

        $disponibilite = $disponibiliteRepository->find(
            $disponibiliteId
        );

        if (!$disponibilite) {
            throw $this->createNotFoundException(
                'Disponibilité introuvable.'
            );
        }


        /*
         
         * 5. Récupération de la formation et du formateur
         
         */

        $formation = $disponibilite->getFormation();
        $formateur = $disponibilite->getFormateur();

        if (!$formation || !$formateur) {
            throw $this->createNotFoundException(
                'La disponibilité est mal configurée.'
            );
        }


        /*
         
         * 6. Vérification du jour choisi
         
         */

        $joursDisponibles = $disponibilite->getJoursSemaine();

        $jourChoisiNormalise = mb_strtolower(
            trim($jourChoisi)
        );

        $joursDisponiblesNormalises = array_map(
            fn ($jour) => mb_strtolower(trim($jour)),
            $joursDisponibles
        );

        if (!in_array(
            $jourChoisiNormalise,
            $joursDisponiblesNormalises,
            true
        )) {
            throw $this->createNotFoundException(
                'Le jour sélectionné n\'est pas disponible pour cette formation.'
            );
        }


        /*
         
         * 7. Correspondance jour français → numéro ISO
         *
         * 1 = lundi
         * 2 = mardi
         * 3 = mercredi
         * 4 = jeudi
         * 5 = vendredi
         * 6 = samedi
         * 7 = dimanche
         
         */

        $joursSemaine = [
            'lundi' => 1,
            'mardi' => 2,
            'mercredi' => 3,
            'jeudi' => 4,
            'vendredi' => 5,
            'samedi' => 6,
            'dimanche' => 7,
        ];

        if (!isset($joursSemaine[$jourChoisiNormalise])) {
            throw $this->createNotFoundException(
                'Jour sélectionné invalide.'
            );
        }

        $numeroJour = $joursSemaine[$jourChoisiNormalise];


        /*
         
         * 8. Récupération de la période
         
         */

        $dateDebut = clone $disponibilite->getDateDebut();
        $dateFin = clone $disponibilite->getDateFin();


        /*
         
         * 9. Recherche des dates correspondant au jour choisi
         
         */

        $datesSeances = [];

        $dateCourante = new \DateTime(
            $dateDebut->format('Y-m-d')
        );

        while ($dateCourante <= $dateFin) {

            if (
                (int) $dateCourante->format('N')
                === $numeroJour
            ) {
                $datesSeances[] = clone $dateCourante;
            }

            $dateCourante->modify('+1 day');
        }


        /*
         
         * 10. Vérification qu'au moins une séance existe
         
         */

        if (count($datesSeances) === 0) {
            throw $this->createNotFoundException(
                'Aucune séance ne correspond au jour sélectionné dans cette période.'
            );
        }


        /*
         
         * 11. Création des réservations
         
         */

        $reservations = [];

        foreach ($datesSeances as $dateSeance) {

            /*
             * -----------------------------------------------------
             * Date + heure de début
             * -----------------------------------------------------
             */

            $dateHeureDebut = new \DateTime(
                $dateSeance->format('Y-m-d')
                . ' '
                . $disponibilite
                    ->getHeureDebut()
                    ->format('H:i:s')
            );


            /*
             * -----------------------------------------------------
             * Date + heure de fin
             * -----------------------------------------------------
             */

            $dateHeureFin = new \DateTime(
                $dateSeance->format('Y-m-d')
                . ' '
                . $disponibilite
                    ->getHeureFin()
                    ->format('H:i:s')
            );


            /*
             * -----------------------------------------------------
             * Création de la réservation
             * -----------------------------------------------------
             */

            $reservation = new Reservation();

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
                'confirmée'
            );

            $reservation->setStudent(
                $student
            );

            $reservation->setFormation(
                $formation
            );

            $reservation->setFormateur(
                $formateur
            );

            $reservation->setDisponibilite(
                $disponibilite
            );

            /*
             * Le lien Jitsi sera généré après le premier flush(),
             * car nous avons besoin de l'ID de la réservation.
             */

            $reservation->setLienVisio(null);

            /*
             * Préparation de la réservation
             */

            $entityManager->persist($reservation);

            $reservations[] = $reservation;
        }


        /*
         
         * 12. Premier flush
         *
         * Permet d'obtenir les IDs des réservations.
         
         */

        $entityManager->flush();


        /*
         
         * 13. Génération des liens Jitsi
         
         */

        foreach ($reservations as $reservation) {

            /*
             * Exemple :
             *
             * Reservation ID = 25
             *
             * Room :
             * Mentor-Reservation-25
             */

            $roomName = 'Mentor-Reservation-'
                . $reservation->getId();

            $lienVisio = 'https://meet.jit.si/'
                . $roomName;

            $reservation->setLienVisio(
                $lienVisio
            );
        }


        /*
         
         * 14. Deuxième flush
         *
         * Enregistre les liens Jitsi.
         
         */

        $entityManager->flush();


        /*
       
         * 15. Message de confirmation
         
         */

        $nombreSeances = count($datesSeances);

        $this->addFlash(
            'success',
            sprintf(
                'Votre réservation a été créée. Vous êtes inscrit à %d séance(s) chaque %s.',
                $nombreSeances,
                ucfirst($jourChoisiNormalise)
            )
        );


        /*
         16. Retour vers le profil du formateur
         */

        return $this->redirectToRoute(
            'app_formateur_profil',
            [
                'id' => $formateur->getId(),
            ]
        );
    }



   
#[Route(
    '/mes-reservations',
    name: 'app_mes_reservations',
    methods: ['GET']
)]
public function mesReservations(): Response
{
    // Récupération de l'utilisateur connecté
    $user = $this->getUser();

    // Vérification de la connexion
    if (!$user) {
        return $this->redirectToRoute('app_login');
    }

    // Récupération du profil étudiant
    $student = $user->getStudent();

    if (!$student) {
        throw $this->createNotFoundException(
            'Aucun profil étudiant associé à votre compte.'
        );
    }

    // Récupération de toutes les réservations de l'étudiant
    $reservations = $student->getReservations();

    // Tableau qui contiendra les réservations regroupées par formation
    $reservationsParFormation = [];

    // Parcours de toutes les réservations
    foreach ($reservations as $reservation) {

        // Récupération de la formation associée à la réservation
        $formation = $reservation->getFormation();

        // Récupération de son identifiant
        $formationId = $formation->getId();

        // Création du groupe si cette formation n'existe pas encore
        if (!isset($reservationsParFormation[$formationId])) {
            $reservationsParFormation[$formationId] = [
                'formation' => $formation,
                'reservations' => [],
            ];
        }

        // Ajout de la réservation au groupe de sa formation
        $reservationsParFormation[$formationId]['reservations'][] = $reservation;
    }

    // Transmission des groupes au template Twig
    return $this->render(
        'reservation/index.html.twig',
        [
            'reservationsParFormation' => $reservationsParFormation,
        ]
    );
}
}