<?php

namespace App\Tests\Unit\Services;

use App\Entity\Formateur;
use App\Entity\Formation;
use App\Entity\Reservation;
use App\Entity\Student;
use App\Service\StudentDashboardService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class StudentDashboardServiceTest extends TestCase
{
    private StudentDashboardService $service;

    protected function setUp(): void
    {
        // « Maintenant » est fixé au 6 novembre 2026, 12 h
        $this->service = new StudentDashboardService(
            new MockClock('2026-11-06 12:00:00')
        );
    }

    private function reservation(
        Student $student,
        Formation $formation,
        string $debut,
        string $statut = 'confirmée',
        ?Formateur $formateur = null
    ): Reservation {
        $reservation = (new Reservation())
            ->setDateReservation(new \DateTime('2026-10-01'))
            ->setDateHeureDebut(new \DateTime($debut))
            ->setDateHeureFin((new \DateTime($debut))->modify('+90 minutes'))
            ->setStatut($statut)
            ->setFormation($formation)
            ->setFormateur($formateur);

        $student->addReservation($reservation);

        return $reservation;
    }

    public function testSansReservationLeTableauEstVide(): void
    {
        $tableau = $this->service->build(new Student());

        self::assertSame(
            ['formations' => 0, 'aVenir' => 0, 'terminees' => 0, 'heures' => 0.0],
            $tableau['stats']
        );
        self::assertNull($tableau['prochaine']);
        self::assertSame([], $tableau['aVenir']);
        self::assertSame([], $tableau['parcours']);
    }

    public function testCalculeStatistiquesEtProgression(): void
    {
        $student = new Student();
        $symfony = (new Formation())->setTitre('Symfony');
        $design = (new Formation())->setTitre('Design');
        $formateur = (new Formateur())->setPrenom('Aminata')->setNom('Diallo');

        // Ajoutées dans le désordre : le service trie par date
        $this->reservation($student, $symfony, '2026-11-16 09:00', formateur: $formateur);
        $passee1 = $this->reservation($student, $symfony, '2026-10-26 09:00', formateur: $formateur);
        $this->reservation($student, $symfony, '2026-11-02 09:00', formateur: $formateur);
        $prochaine = $this->reservation($student, $symfony, '2026-11-09 09:00', formateur: $formateur);
        $this->reservation($student, $design, '2026-11-20 14:00');
        $this->reservation($student, $design, '2026-11-01 14:00', 'annulée');

        $tableau = $this->service->build($student);

        self::assertSame(2, $tableau['stats']['formations']);
        self::assertSame(3, $tableau['stats']['aVenir']);
        self::assertSame(2, $tableau['stats']['terminees']);
        self::assertSame(3.0, $tableau['stats']['heures']);

        self::assertSame($prochaine, $tableau['prochaine']);
        self::assertCount(3, $tableau['aVenir']);

        [$ligneSymfony, $ligneDesign] = $tableau['parcours'];

        self::assertSame($symfony, $ligneSymfony['formation']);
        self::assertSame($formateur, $ligneSymfony['formateur']);
        self::assertSame(4, $ligneSymfony['total']);
        self::assertSame(2, $ligneSymfony['terminees']);
        self::assertSame(50, $ligneSymfony['progression']);
        self::assertSame($prochaine, $ligneSymfony['prochaine']);

        // La séance annulée n'est pas comptée
        self::assertSame(1, $ligneDesign['total']);
        self::assertSame(0, $ligneDesign['progression']);

        self::assertNotContains($passee1, $tableau['aVenir']);
    }

    public function testLimiteLeNombreDeSeancesAVenir(): void
    {
        $student = new Student();
        $formation = (new Formation())->setTitre('Symfony');

        foreach (range(1, 8) as $semaine) {
            $this->reservation($student, $formation, sprintf('2026-11-06 14:00 +%d weeks', $semaine));
        }

        $tableau = $this->service->build($student, 5);

        self::assertSame(8, $tableau['stats']['aVenir']);
        self::assertCount(5, $tableau['aVenir']);
    }
}
