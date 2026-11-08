<?php

namespace App\Service;

use App\Entity\Disponibilite;
use App\Entity\Formateur;
use App\Entity\Formation;
use App\Entity\Reservation;
use Symfony\Component\Clock\ClockInterface;

/**
 * Prépare les données de l'espace formateur :
 * statistiques, prochaines séances, disponibilités et formations animées.
 *
 * Les réservations annulées sont ignorées partout.
 */
class FormateurDashboardService
{
    public function __construct(
        private ClockInterface $clock
    ) {
    }

    /**
     * @return array{
     *     stats: array{formations: int, etudiants: int, aVenir: int, paiements: float},
     *     prochaine: ?Reservation,
     *     aVenir: list<Reservation>,
     *     disponibilites: list<array{disponibilite: Disponibilite, active: bool, reservations: int}>,
     *     formations: list<array{formation: Formation, seances: int, etudiants: int}>
     * }
     */
    public function build(Formateur $formateur, int $limiteAVenir = 6): array
    {
        $maintenant = $this->clock->now();
        $aujourdhui = $maintenant->setTime(0, 0);

        $actives = array_values(array_filter(
            $formateur->getReservations()->toArray(),
            static fn (Reservation $reservation): bool => $reservation->getStatut() !== 'annulée'
                && $reservation->getDateHeureDebut() !== null
        ));

        usort(
            $actives,
            static fn (Reservation $a, Reservation $b): int => $a->getDateHeureDebut() <=> $b->getDateHeureDebut()
        );

        $aVenir = [];
        $etudiants = [];
        $paiements = 0.0;
        $parFormation = [];

        foreach ($actives as $reservation) {
            $fin = $reservation->getDateHeureFin() ?? $reservation->getDateHeureDebut();

            if ($fin >= $maintenant) {
                $aVenir[] = $reservation;
            }

            $student = $reservation->getStudent();

            if ($student !== null) {
                $etudiants[spl_object_id($student)] = true;
            }

            if ($reservation->getPaiement()?->getMontant() !== null) {
                $paiements += (float) $reservation->getPaiement()->getMontant();
            }

            $formationId = $reservation->getFormation() !== null ? spl_object_id($reservation->getFormation()) : null;

            if ($formationId !== null) {
                $parFormation[$formationId]['seances'] = ($parFormation[$formationId]['seances'] ?? 0) + 1;

                if ($student !== null) {
                    $parFormation[$formationId]['etudiants'][spl_object_id($student)] = true;
                }
            }
        }

        $formations = [];

        foreach ($formateur->getFormation() as $formation) {
            $formations[] = [
                'formation' => $formation,
                'seances' => $parFormation[spl_object_id($formation)]['seances'] ?? 0,
                'etudiants' => count($parFormation[spl_object_id($formation)]['etudiants'] ?? []),
            ];
        }

        $disponibilites = [];

        foreach ($formateur->getDisponibilites() as $disponibilite) {
            $disponibilites[] = [
                'disponibilite' => $disponibilite,
                'active' => $disponibilite->getDateFin() !== null && $disponibilite->getDateFin() >= $aujourdhui,
                'reservations' => count(array_filter(
                    $actives,
                    static fn (Reservation $reservation): bool => $reservation->getDisponibilite() === $disponibilite
                )),
            ];
        }

        // Les périodes en cours ou à venir d'abord
        usort(
            $disponibilites,
            static fn (array $a, array $b): int => [$b['active'], $a['disponibilite']->getDateDebut()]
                <=> [$a['active'], $b['disponibilite']->getDateDebut()]
        );

        return [
            'stats' => [
                'formations' => count($formations),
                'etudiants' => count($etudiants),
                'aVenir' => count($aVenir),
                'paiements' => $paiements,
            ],
            'prochaine' => $aVenir[0] ?? null,
            'aVenir' => array_slice($aVenir, 0, $limiteAVenir),
            'disponibilites' => $disponibilites,
            'formations' => $formations,
        ];
    }
}
