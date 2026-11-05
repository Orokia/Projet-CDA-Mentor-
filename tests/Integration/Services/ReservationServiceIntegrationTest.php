<?php

namespace App\Tests\Integration\Services;

use App\Entity\Disponibilite;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReservationServiceIntegrationTest extends KernelTestCase
{
    use EntitesDeTestTrait;

    private ReservationService $reservationService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->reservationService = static::getContainer()
            ->get(ReservationService::class);
    }

    public function testCreerReservationsEnregistreUneSeanceParOccurrence(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation()
        );

        $reservations = $this->reservationService->creerReservations(
            $student,
            $disponibilite->getId(),
            'lundi'
        );

        self::assertCount(2, $reservations);

        // On relit depuis la base pour vérifier ce qui a vraiment été écrit
        $this->em()->clear();
        $enBase = static::getContainer()
            ->get(ReservationRepository::class)
            ->findBy(['student' => $student->getId()], ['dateHeureDebut' => 'ASC']);

        self::assertCount(2, $enBase);
        self::assertSame(
            '2026-10-19 09:00',
            $enBase[0]->getDateHeureDebut()->format('Y-m-d H:i')
        );
        self::assertSame(
            '2026-10-26 10:00',
            $enBase[1]->getDateHeureFin()->format('Y-m-d H:i')
        );

        foreach ($enBase as $reservation) {
            // Le lien est enregistré au second flush, avec l'identifiant réel
            self::assertStringStartsWith(
                'https://meet.jit.si/Mentor-Reservation-' . $reservation->getId() . '-',
                $reservation->getLienVisio()
            );
        }

        self::assertNotSame($enBase[0]->getLienVisio(), $enBase[1]->getLienVisio());
    }

    public function testCreerReservationsRefuseUnDoublon(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation()
        );

        $this->reservationService->creerReservations($student, $disponibilite->getId(), 'lundi');

        // La recherche du doublon (findOneBy sur un DateTime) doit fonctionner en vraie base
        try {
            $this->reservationService->creerReservations($student, $disponibilite->getId(), 'lundi');
            self::fail('Une deuxième réservation du même créneau aurait dû être refusée.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Vous avez déjà réservé ce créneau.', $exception->getMessage());
        }

        self::assertSame(
            2,
            $this->em()->getRepository(Reservation::class)->count([])
        );
    }

    public function testCreerReservationsEnregistreLeJourCommePlusDisponible(): void
    {
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation(),
            ['lundi' => 'disponible', 'mardi' => 'disponible']
        );

        $this->reservationService->creerReservations(
            $this->creerUtilisateur()->getStudent(),
            $disponibilite->getId(),
            'lundi'
        );

        // On relit depuis la base : le statut a bien été écrit
        $this->em()->clear();
        $enBase = $this->em()->find(Disponibilite::class, $disponibilite->getId());

        self::assertSame(
            ['lundi' => 'plus disponible', 'mardi' => 'disponible'],
            $enBase->getJoursSemaine()
        );
    }

    public function testCreerReservationsRefuseLeJourAUnAutreEtudiant(): void
    {
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation()
        );

        $this->reservationService->creerReservations(
            $this->creerUtilisateur()->getStudent(),
            $disponibilite->getId(),
            'lundi'
        );

        try {
            $this->reservationService->creerReservations(
                $this->creerUtilisateur('autre.etudiant@example.test')->getStudent(),
                $disponibilite->getId(),
                'lundi'
            );
            self::fail('Un jour déjà réservé aurait dû être refusé à un autre étudiant.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame("Ce jour n'est plus disponible.", $exception->getMessage());
        }

        self::assertSame(
            2,
            $this->em()->getRepository(Reservation::class)->count([])
        );
    }

    public function testDisponibiliteIntrouvableDeclencheUneException(): void
    {
        $student = $this->creerUtilisateur()->getStudent();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disponibilité introuvable.');

        $this->reservationService->creerReservations($student, 999999, 'lundi');
    }

    public function testCreerReservationsAucuneDateCorrespondante(): void
    {
        $student = $this->creerUtilisateur()->getStudent();

        // Du mardi 20 au dimanche 25 octobre 2026 : aucun lundi
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation(),
            ['lundi' => 'disponible', 'mardi' => 'disponible'],
            '2026-10-20',
            '2026-10-25'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucune séance ne correspond au jour choisi.');

        $this->reservationService->creerReservations(
            $student,
            $disponibilite->getId(),
            'lundi'
        );
    }
}
