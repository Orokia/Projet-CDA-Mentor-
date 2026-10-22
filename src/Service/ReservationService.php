<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Student;
use App\Repository\DisponibiliteRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReservationService
{
    public function __construct(
        private DisponibiliteRepository $disponibiliteRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Crée les réservations pour chaque occurrence
     * du jour choisi dans la période de disponibilité.
     *
     * @return Reservation[] Liste des réservations créées
     */
    public function creerReservations(
        Student $student,
        int $disponibiliteId,
        string $jourChoisi
    ): array {
        // Recherche de la disponibilité demandée
        $disponibilite = $this->disponibiliteRepository
            ->find($disponibiliteId);

        if (!$disponibilite) {
            throw new \RuntimeException(
                'Disponibilité introuvable.'
            );
        }

        // Récupération de la formation et du formateur associés
        $formation = $disponibilite->getFormation();
        $formateur = $disponibilite->getFormateur();

        if (!$formation || !$formateur) {
            throw new \RuntimeException(
                'La disponibilité est mal configurée.'
            );
        }

        // Normalisation du jour pour éviter les différences de casse
        $jourNormalise = mb_strtolower(trim($jourChoisi));

        $joursDisponibles = array_map(
            fn ($jour) => mb_strtolower(trim($jour)),
            $disponibilite->getJoursSemaine()
        );

        // Vérification que le jour demandé est autorisé
        if (!in_array($jourNormalise, $joursDisponibles, true)) {
            throw new \InvalidArgumentException(
                "Le jour choisi n'est pas disponible."
            );
        }

        // Correspondance entre les jours français et leur numéro ISO
        $joursSemaine = [
            'lundi' => 1,
            'mardi' => 2,
            'mercredi' => 3,
            'jeudi' => 4,
            'vendredi' => 5,
            'samedi' => 6,
            'dimanche' => 7,
        ];

        if (!isset($joursSemaine[$jourNormalise])) {
            throw new \InvalidArgumentException(
                'Jour sélectionné invalide.'
            );
        }

        // Détermination du jour recherché
        $numeroJour = $joursSemaine[$jourNormalise];

        // Récupération de la période de disponibilité
        $dateDebut = clone $disponibilite->getDateDebut();
        $dateFin = clone $disponibilite->getDateFin();

        // Recherche de toutes les dates correspondant au jour choisi
        $datesSeances = [];
        $dateCourante = new \DateTime(
            $dateDebut->format('Y-m-d')
        );

        while ($dateCourante <= $dateFin) {
            if ((int) $dateCourante->format('N') === $numeroJour) {
                $datesSeances[] = clone $dateCourante;
            }

            $dateCourante->modify('+1 day');
        }

        if (count($datesSeances) === 0) {
            throw new \RuntimeException(
                'Aucune séance ne correspond au jour choisi.'
            );
        }

        // Création d'une réservation pour chaque date trouvée
        $reservations = [];

        foreach ($datesSeances as $dateSeance) {
            // Combinaison de la date et de l'heure de début
            $dateHeureDebut = new \DateTime(
                $dateSeance->format('Y-m-d') . ' ' .
                $disponibilite->getHeureDebut()->format('H:i:s')
            );

            // Combinaison de la date et de l'heure de fin
            $dateHeureFin = new \DateTime(
                $dateSeance->format('Y-m-d') . ' ' .
                $disponibilite->getHeureFin()->format('H:i:s')
            );

            // Création et association de la réservation
            $reservation = new Reservation();
            $reservation->setDateReservation(new \DateTime());
            $reservation->setDateHeureDebut($dateHeureDebut);
            $reservation->setDateHeureFin($dateHeureFin);
            $reservation->setStatut('confirmée');
            $reservation->setStudent($student);
            $reservation->setFormation($formation);
            $reservation->setFormateur($formateur);
            $reservation->setDisponibilite($disponibilite);

            // Le lien sera ajouté après l'obtention de l'identifiant
            $reservation->setLienVisio(null);

            // Préparation de l'enregistrement en base
            $this->entityManager->persist($reservation);
            $reservations[] = $reservation;
        }

        // Premier enregistrement pour obtenir les identifiants
        $this->entityManager->flush();

        // Génération du lien Jitsi pour chaque réservation
        foreach ($reservations as $reservation) {
            $roomName = 'Mentor-Reservation-' . $reservation->getId();

            $reservation->setLienVisio(
                'https://meet.jit.si/' . $roomName
            );
        }

        // Deuxième enregistrement pour sauvegarder les liens
        $this->entityManager->flush();

        return $reservations;
    }

    /**
     * Regroupe les réservations de l'étudiant par formation.
     *
     * @param iterable<Reservation> $reservations
     */
    public function regrouperParFormation(iterable $reservations): array
    {
        $groupes = [];

        foreach ($reservations as $reservation) {
            // Récupération de la formation de la réservation
            $formation = $reservation->getFormation();

            if (!$formation) {
                continue;
            }

            $formationId = $formation->getId();

            // Création du groupe si nécessaire
            if (!isset($groupes[$formationId])) {
                $groupes[$formationId] = [
                    'formation' => $formation,
                    'reservations' => [],
                ];
            }

            // Ajout de la réservation à son groupe
            $groupes[$formationId]['reservations'][] = $reservation;
        }

        return $groupes;
    }
}
