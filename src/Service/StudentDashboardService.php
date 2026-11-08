<?php

namespace App\Service;

use App\Entity\Formateur;
use App\Entity\Formation;
use App\Entity\Reservation;
use App\Entity\Student;
use Symfony\Component\Clock\ClockInterface;

/**
 * Prépare les données du tableau de bord de l'étudiant :
 * statistiques, prochaine séance, séances à venir et progression par formation.
 *
 * Les réservations annulées sont ignorées partout.
 * Une séance est « terminée » quand son heure de fin est passée.
 */
class StudentDashboardService
{
    public function __construct(
        private ClockInterface $clock
    ) {
    }

    /**
     * @return array{
     *     stats: array{formations: int, aVenir: int, terminees: int, heures: float},
     *     prochaine: ?Reservation,
     *     aVenir: list<Reservation>,
     *     parcours: list<array{formation: Formation, formateur: ?Formateur, total: int, terminees: int, progression: int, prochaine: ?Reservation}>
     * }
     */
    public function build(Student $student, int $limiteAVenir = 5): array
    {
        $maintenant = $this->clock->now();

        $actives = array_values(array_filter(
            $student->getReservations()->toArray(),
            static fn (Reservation $reservation): bool => $reservation->getStatut() !== 'annulée'
                && $reservation->getDateHeureDebut() !== null
        ));

        // Ordre chronologique
        usort(
            $actives,
            static fn (Reservation $a, Reservation $b): int => $a->getDateHeureDebut() <=> $b->getDateHeureDebut()
        );

        $aVenir = [];
        $terminees = 0;
        $heures = 0.0;
        $parcours = [];

        foreach ($actives as $reservation) {
            $estTerminee = $this->estTerminee($reservation, $maintenant);

            if ($estTerminee) {
                $terminees++;
                $heures += $this->dureeEnHeures($reservation);
            } else {
                $aVenir[] = $reservation;
            }

            $formation = $reservation->getFormation();

            if ($formation === null) {
                continue;
            }

            $id = spl_object_id($formation);

            $parcours[$id] ??= [
                'formation' => $formation,
                'formateur' => $reservation->getFormateur(),
                'total' => 0,
                'terminees' => 0,
                'progression' => 0,
                'prochaine' => null,
            ];

            $parcours[$id]['total']++;

            if ($estTerminee) {
                $parcours[$id]['terminees']++;
            } elseif ($parcours[$id]['prochaine'] === null) {
                $parcours[$id]['prochaine'] = $reservation;
            }
        }

        foreach ($parcours as &$ligne) {
            $ligne['progression'] = (int) round($ligne['terminees'] / $ligne['total'] * 100);
        }
        unset($ligne);

        return [
            'stats' => [
                'formations' => count($parcours),
                'aVenir' => count($aVenir),
                'terminees' => $terminees,
                'heures' => round($heures, 1),
            ],
            'prochaine' => $aVenir[0] ?? null,
            'aVenir' => array_slice($aVenir, 0, $limiteAVenir),
            'parcours' => array_values($parcours),
        ];
    }

    private function estTerminee(Reservation $reservation, \DateTimeImmutable $maintenant): bool
    {
        $fin = $reservation->getDateHeureFin() ?? $reservation->getDateHeureDebut();

        return $fin < $maintenant;
    }

    private function dureeEnHeures(Reservation $reservation): float
    {
        $debut = $reservation->getDateHeureDebut();
        $fin = $reservation->getDateHeureFin();

        if ($debut === null || $fin === null || $fin <= $debut) {
            return 0.0;
        }

        return ($fin->getTimestamp() - $debut->getTimestamp()) / 3600;
    }
}
