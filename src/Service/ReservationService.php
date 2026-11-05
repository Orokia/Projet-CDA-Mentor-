<?php

namespace App\Service;

use App\Entity\Disponibilite;
use App\Entity\Reservation;
use App\Entity\Student;
use App\Repository\DisponibiliteRepository;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ReservationService
{
    public function __construct(
        private DisponibiliteRepository $disponibiliteRepository,
        private EntityManagerInterface $entityManager,
        private ReservationRepository $reservationRepository,
        #[Autowire('%jt_domain%')]
        private string $jitsiDomain
    ) {
    }

    /**
     * Crée les réservations pour chaque occurrence
     * du jour choisi dans la période de disponibilité.
     *
     * Tout se fait dans une seule transaction : en cas d'erreur, à n'importe
     * quelle étape, aucune réservation n'est enregistrée et le jour reste
     * « disponible ».
     *
     * @return Reservation[] Liste des réservations créées
     */
    public function creerReservations(
        Student $student,
        int $disponibiliteId,
        string $jourChoisi
    ): array {
        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();

        try {
            $reservations = $this->enregistrerReservations($student, $disponibiliteId, $jourChoisi);
            $connexion->commit();
        } catch (\Throwable $exception) {
            // Annule aussi le premier flush (réservations + statut du jour)
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            throw $exception;
        }

        return $reservations;
    }

    /**
     * @return Reservation[]
     */
    private function enregistrerReservations(
        Student $student,
        int $disponibiliteId,
        string $jourChoisi
    ): array {
        // Verrou d'écriture (SELECT … FOR UPDATE) jusqu'à la fin de la transaction.
        // Deux étudiants qui réservent le même jour en même temps sont traités
        // l'un après l'autre : le second lit le statut enregistré par le premier
        // et reçoit « Ce jour n'est plus disponible. ».
        $disponibilite = $this->disponibiliteRepository
            ->find($disponibiliteId, LockMode::PESSIMISTIC_WRITE);

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

        // joursSemaine : clé = nom du jour, valeur = statut du jour.
        // Les entrées mal formées (ancien format en liste) sont ignorées.
        $statutsParJour = [];

        foreach ($disponibilite->getJoursSemaine() as $jour => $statut) {
            if (!is_string($jour) || !is_string($statut)) {
                continue;
            }

            $statutsParJour[mb_strtolower(trim($jour))] = mb_strtolower(trim($statut));
        }

        // Vérification que le jour demandé est proposé
        if (!isset($statutsParJour[$jourNormalise])) {
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

        // Refus si l'étudiant a déjà réservé ce créneau
        $premiereSeance = new \DateTime(
            $datesSeances[0]->format('Y-m-d') . ' ' .
            $disponibilite->getHeureDebut()->format('H:i:s')
        );

        $dejaReservee = $this->reservationRepository->findOneBy([
            'student' => $student,
            'disponibilite' => $disponibilite,
            'dateHeureDebut' => $premiereSeance,
        ]);

        if ($dejaReservee !== null) {
            throw new \RuntimeException(
                'Vous avez déjà réservé ce créneau.'
            );
        }

        // Seuls les jours au statut « disponible » peuvent être réservés.
        // Vérifié après le doublon : l'étudiant qui a déjà réservé ce jour
        // reçoit le message qui le concerne.
        if ($statutsParJour[$jourNormalise] !== Disponibilite::STATUT_DISPONIBLE) {
            throw new \InvalidArgumentException(
                "Ce jour n'est plus disponible."
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

        // La réservation est confirmée : le jour choisi n'est plus proposé.
        // Enregistré dans le même flush que les réservations.
        $disponibilite->changerStatutJour(
            $jourNormalise,
            Disponibilite::STATUT_PLUS_DISPONIBLE
        );

        // Premier enregistrement pour obtenir les identifiants
        $this->entityManager->flush();

        // Génération du lien Jitsi pour chaque réservation.
        // Le suffixe aléatoire empêche de deviner l'URL d'une salle.
        foreach ($reservations as $reservation) {
            $roomName = 'Mentor-Reservation-' . $reservation->getId()
                . '-' . bin2hex(random_bytes(8));

            $reservation->setLienVisio(
                rtrim($this->jitsiDomain, '/') . '/' . $roomName
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
