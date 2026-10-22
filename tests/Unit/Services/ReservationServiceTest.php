<?php

namespace App\Tests\Unit\Services;

use App\Entity\Disponibilite;
use App\Entity\Formation;
use App\Entity\Formateur;
use App\Entity\Reservation;
use App\Entity\Student;
use App\Repository\DisponibiliteRepository;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
class ReservationServiceTest extends TestCase
{
    private DisponibiliteRepository $disponibiliteRepository;
    private EntityManagerInterface $entityManager;
    private ReservationService $reservationService;

    protected function setUp(): void
    {
        // Création des mocks des dépendances
        $this->disponibiliteRepository = $this->createMock(
            DisponibiliteRepository::class
        );

        $this->entityManager = $this->createMock(
            EntityManagerInterface::class
        );

        // Injection des dépendances dans le service
        $this->reservationService = new ReservationService(
            $this->disponibiliteRepository,
            $this->entityManager
        );
    }

    public function testCreerReservationsDisponibiliteIntrouvable(): void
    {
        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disponibilité introuvable.');

        $this->reservationService->creerReservations(
            $this->createMock(Student::class),
            999,
            'lundi'
        );
    }

    public function testCreerReservationsDisponibiliteMalConfiguree(): void
    {
        $disponibilite = $this->createMock(Disponibilite::class);

        $disponibilite
            ->method('getFormation')
            ->willReturn(null);

        $disponibilite
            ->method('getFormateur')
            ->willReturn($this->createMock(Formateur::class));

        $this->disponibiliteRepository
            ->method('find')
            ->with(1)
            ->willReturn($disponibilite);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'La disponibilité est mal configurée.'
        );

        $this->reservationService->creerReservations(
            $this->createMock(Student::class),
            1,
            'lundi'
        );
    }

    public function testCreerReservationsJourNonDisponible(): void
    {
        $disponibilite = $this->createMock(Disponibilite::class);

        $disponibilite
            ->method('getFormation')
            ->willReturn($this->createMock(Formation::class));

        $disponibilite
            ->method('getFormateur')
            ->willReturn($this->createMock(Formateur::class));

        $disponibilite
            ->method('getJoursSemaine')
            ->willReturn(['mardi']);

        $this->disponibiliteRepository
            ->method('find')
            ->willReturn($disponibilite);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Le jour choisi n'est pas disponible."
        );

        $this->reservationService->creerReservations(
            $this->createMock(Student::class),
            1,
            'lundi'
        );
    }

    public function testCreerReservationsJourInvalide(): void
    {
        $disponibilite = $this->createMock(Disponibilite::class);

        $disponibilite
            ->method('getFormation')
            ->willReturn($this->createMock(Formation::class));

        $disponibilite
            ->method('getFormateur')
            ->willReturn($this->createMock(Formateur::class));

        $disponibilite
            ->method('getJoursSemaine')
            ->willReturn(['funday']);

        $this->disponibiliteRepository
            ->method('find')
            ->willReturn($disponibilite);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Jour sélectionné invalide.');

        $this->reservationService->creerReservations(
            $this->createMock(Student::class),
            1,
            'funday'
        );
    }

    public function testCreerReservationsAucuneDateCorrespondante(): void
    {
        $disponibilite = $this->createMock(Disponibilite::class);

        $disponibilite
            ->method('getFormation')
            ->willReturn($this->createMock(Formation::class));

        $disponibilite
            ->method('getFormateur')
            ->willReturn($this->createMock(Formateur::class));

        $disponibilite
            ->method('getJoursSemaine')
            ->willReturn(['lundi']);

        // Cette période ne contient aucun lundi
        $disponibilite
            ->method('getDateDebut')
            ->willReturn(new \DateTime('2026-10-20'));

        $disponibilite
            ->method('getDateFin')
            ->willReturn(new \DateTime('2026-10-25'));

        $this->disponibiliteRepository
            ->method('find')
            ->willReturn($disponibilite);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Aucune séance ne correspond au jour choisi.'
        );

        $this->reservationService->creerReservations(
            $this->createMock(Student::class),
            1,
            'lundi'
        );
    }

    public function testCreerReservationsAvecSucces(): void
    {
        $student = $this->createMock(Student::class);
        $formation = $this->createMock(Formation::class);
        $formateur = $this->createMock(Formateur::class);
        $disponibilite = $this->createMock(Disponibilite::class);

        $disponibilite
            ->method('getFormation')
            ->willReturn($formation);

        $disponibilite
            ->method('getFormateur')
            ->willReturn($formateur);

        $disponibilite
            ->method('getJoursSemaine')
            ->willReturn(['lundi']);

        // Deux lundis sont présents dans cette période
        $disponibilite
            ->method('getDateDebut')
            ->willReturn(new \DateTime('2026-10-19'));

        $disponibilite
            ->method('getDateFin')
            ->willReturn(new \DateTime('2026-10-26'));

        $disponibilite
            ->method('getHeureDebut')
            ->willReturn(new \DateTime('09:00:00'));

        $disponibilite
            ->method('getHeureFin')
            ->willReturn(new \DateTime('10:00:00'));

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($disponibilite);

        // Deux réservations doivent être persistées
        $reservationsEnregistrees = [];

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(
                static function (object $entity) use (
                    &$reservationsEnregistrees
                ): void {
                    $reservationsEnregistrees[] = $entity;
                }
            );

        // Un flush avant et un après la génération des liens
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        // Exécution du service
        $reservations = $this->reservationService->creerReservations(
            $student,
            1,
            ' LUNDI '
        );

        // Vérification du nombre de réservations
        $this->assertCount(2, $reservations);
        $this->assertCount(2, $reservationsEnregistrees);

        foreach ($reservations as $reservation) {
            $this->assertInstanceOf(Reservation::class, $reservation);
            $this->assertSame($student, $reservation->getStudent());
            $this->assertSame($formation, $reservation->getFormation());
            $this->assertSame($formateur, $reservation->getFormateur());
            $this->assertSame(
                $disponibilite,
                $reservation->getDisponibilite()
            );

            $this->assertSame('confirmée', $reservation->getStatut());
            $this->assertNotNull($reservation->getDateReservation());
            $this->assertNotNull($reservation->getDateHeureDebut());
            $this->assertNotNull($reservation->getDateHeureFin());

            // Le lien est construit avec l'identifiant de la réservation
            $this->assertStringStartsWith(
                'https://meet.jit.si/Mentor-Reservation-',
                $reservation->getLienVisio()
            );
        }

        // Vérification des horaires et des dates des séances
        $this->assertSame(
            '2026-10-19 09:00:00',
            $reservations[0]->getDateHeureDebut()->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-10-26 09:00:00',
            $reservations[1]->getDateHeureDebut()->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-10-19 10:00:00',
            $reservations[0]->getDateHeureFin()->format('Y-m-d H:i:s')
        );


    }

    public function testRegrouperParFormation(): void
    {
        $formation1 = $this->createMock(Formation::class);
        $formation2 = $this->createMock(Formation::class);

        $formation1
            ->method('getId')
            ->willReturn(1);

        $formation2
            ->method('getId')
            ->willReturn(2);

        $reservation1 = $this->createMock(Reservation::class);
        $reservation2 = $this->createMock(Reservation::class);
        $reservation3 = $this->createMock(Reservation::class);

        $reservation1
            ->method('getFormation')
            ->willReturn($formation1);

        $reservation2
            ->method('getFormation')
            ->willReturn($formation1);

        $reservation3
            ->method('getFormation')
            ->willReturn($formation2);

        $resultat = $this->reservationService->regrouperParFormation([
            $reservation1,
            $reservation2,
            $reservation3,
        ]);

        // Deux groupes doivent être créés
        $this->assertCount(2, $resultat);

        $this->assertSame($formation1, $resultat[1]['formation']);
        $this->assertCount(2, $resultat[1]['reservations']);
        $this->assertSame($formation2, $resultat[2]['formation']);
        $this->assertCount(1, $resultat[2]['reservations']);

        $this->assertSame(
            [$reservation1, $reservation2],
            $resultat[1]['reservations']
        );
    }

    public function testRegrouperParFormationIgnoreReservationsSansFormation(): void
    {
        $reservation = $this->createMock(Reservation::class);

        $reservation
            ->method('getFormation')
            ->willReturn(null);

        $resultat = $this->reservationService->regrouperParFormation([
            $reservation,
        ]);

        $this->assertSame([], $resultat);
    }

    public function testRegrouperParFormationAvecListeVide(): void
    {
        $resultat = $this->reservationService->regrouperParFormation([]);

        $this->assertSame([], $resultat);
    }

   

}
