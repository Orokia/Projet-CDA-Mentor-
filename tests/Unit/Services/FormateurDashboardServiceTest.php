<?php

namespace App\Tests\Unit\Services;

use App\Entity\Disponibilite;
use App\Entity\Formateur;
use App\Entity\Formation;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\Student;
use App\Service\FormateurDashboardService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class FormateurDashboardServiceTest extends TestCase
{
    private FormateurDashboardService $service;

    protected function setUp(): void
    {
        $this->service = new FormateurDashboardService(
            new MockClock('2026-11-06 12:00:00')
        );
    }

    private function reservation(
        Formateur $formateur,
        Formation $formation,
        Student $student,
        string $debut,
        string $statut = 'confirmée',
        ?Disponibilite $disponibilite = null,
        ?string $montant = null
    ): Reservation {
        $reservation = (new Reservation())
            ->setDateReservation(new \DateTime('2026-10-01'))
            ->setDateHeureDebut(new \DateTime($debut))
            ->setDateHeureFin((new \DateTime($debut))->modify('+1 hour'))
            ->setStatut($statut)
            ->setFormation($formation)
            ->setStudent($student)
            ->setDisponibilite($disponibilite);

        if ($montant !== null) {
            $reservation->setPaiement(
                (new Paiement())->setMontant($montant)->setMode('carte')->setStatut('payé')
            );
        }

        $formateur->addReservation($reservation);

        return $reservation;
    }

    public function testSansDonneesLeTableauEstVide(): void
    {
        $tableau = $this->service->build(new Formateur());

        self::assertSame(
            ['formations' => 0, 'etudiants' => 0, 'aVenir' => 0, 'paiements' => 0.0],
            $tableau['stats']
        );
        self::assertNull($tableau['prochaine']);
        self::assertSame([], $tableau['disponibilites']);
        self::assertSame([], $tableau['formations']);
    }

    public function testCalculeStatistiquesFormationsEtDisponibilites(): void
    {
        $formateur = new Formateur();
        $symfony = (new Formation())->setTitre('Symfony');
        $design = (new Formation())->setTitre('Design');
        $formateur->addFormation($symfony);
        $formateur->addFormation($design);

        $ancienne = (new Disponibilite())
            ->setDateDebut(new \DateTime('2026-09-01'))
            ->setDateFin(new \DateTime('2026-09-30'));
        $enCours = (new Disponibilite())
            ->setDateDebut(new \DateTime('2026-10-19'))
            ->setDateFin(new \DateTime('2026-11-30'));
        $formateur->addDisponibilite($ancienne);
        $formateur->addDisponibilite($enCours);

        $amadou = new Student();
        $awa = new Student();

        $this->reservation($formateur, $symfony, $amadou, '2026-10-26 09:00', disponibilite: $enCours, montant: '100.00');
        $prochaine = $this->reservation($formateur, $symfony, $amadou, '2026-11-09 09:00', disponibilite: $enCours, montant: '100.00');
        $this->reservation($formateur, $symfony, $awa, '2026-11-10 09:00', disponibilite: $enCours);
        $this->reservation($formateur, $symfony, $awa, '2026-11-17 09:00', 'annulée', $enCours, '50.00');

        $tableau = $this->service->build($formateur);

        self::assertSame(2, $tableau['stats']['formations']);
        self::assertSame(2, $tableau['stats']['etudiants']);
        self::assertSame(2, $tableau['stats']['aVenir']);
        self::assertSame(200.0, $tableau['stats']['paiements']);
        self::assertSame($prochaine, $tableau['prochaine']);

        self::assertSame(
            [
                ['formation' => $symfony, 'seances' => 3, 'etudiants' => 2],
                ['formation' => $design, 'seances' => 0, 'etudiants' => 0],
            ],
            $tableau['formations']
        );

        // La période en cours passe avant la période terminée
        self::assertSame($enCours, $tableau['disponibilites'][0]['disponibilite']);
        self::assertTrue($tableau['disponibilites'][0]['active']);
        self::assertSame(3, $tableau['disponibilites'][0]['reservations']);
        self::assertFalse($tableau['disponibilites'][1]['active']);
    }
}
