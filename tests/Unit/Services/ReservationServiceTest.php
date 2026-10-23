<?php

namespace App\Tests\Unit\Services;

use App\Entity\Disponibilite;
use App\Entity\Formation;
use App\Entity\Formateur;
use App\Entity\Reservation;
use App\Entity\Student;
use App\Repository\DisponibiliteRepository;
use App\Repository\ReservationRepository;
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
            $this->entityManager,
            $this->createStub(ReservationRepository::class),
            'https://meet.jit.si'
        );
    }

    // Le service ne doit rien enregistrer en base.
    private function attendreAucuneEcriture(): void
    {
        $this->entityManager
            ->expects($this->never())
            ->method($this->anything());
    }

    // Le service ne doit pas interroger les disponibilités.
    private function attendreAucuneLecture(): void
    {
        $this->disponibiliteRepository
            ->expects($this->never())
            ->method($this->anything());
    }

    public function testCreerReservationsDisponibiliteIntrouvable(): void
    {
        $this->attendreAucuneEcriture();

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disponibilité introuvable.');

        $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            999,
            'lundi'
        );
    }

    public function testCreerReservationsDisponibiliteMalConfiguree(): void
    {
        $this->attendreAucuneEcriture();

        $disponibilite = $this->createStub(Disponibilite::class);

        $disponibilite
            ->method('getFormation')
            ->willReturn(null);

        $disponibilite
            ->method('getFormateur')
            ->willReturn($this->createStub(Formateur::class));

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($disponibilite);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'La disponibilité est mal configurée.'
        );

        $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            1,
            'lundi'
        );
    }

    public function testCreerReservationsJourNonDisponible(): void
    {
        $this->attendreAucuneEcriture();

        $disponibilite = $this->createStub(Disponibilite::class);

        $disponibilite
            ->method('getFormation')
            ->willReturn($this->createStub(Formation::class));

        $disponibilite
            ->method('getFormateur')
            ->willReturn($this->createStub(Formateur::class));

        $disponibilite
            ->method('getJoursSemaine')
            ->willReturn(['mardi']);

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($disponibilite);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Le jour choisi n'est pas disponible."
        );

        $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            1,
            'lundi'
        );
    }

    public function testCreerReservationsJourInvalide(): void
    {
        $this->attendreAucuneEcriture();

        $disponibilite = $this->createStub(Disponibilite::class);

        $disponibilite
            ->method('getFormation')
            ->willReturn($this->createStub(Formation::class));

        $disponibilite
            ->method('getFormateur')
            ->willReturn($this->createStub(Formateur::class));

        $disponibilite
            ->method('getJoursSemaine')
            ->willReturn(['funday']);

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($disponibilite);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Jour sélectionné invalide.');

        $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            1,
            'funday'
        );
    }

    public function testCreerReservationsAucuneDateCorrespondante(): void
    {
        $this->attendreAucuneEcriture();

        $disponibilite = $this->createStub(Disponibilite::class);

        $disponibilite
            ->method('getFormation')
            ->willReturn($this->createStub(Formation::class));

        $disponibilite
            ->method('getFormateur')
            ->willReturn($this->createStub(Formateur::class));

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
            ->expects($this->once())
            ->method('find')
            ->willReturn($disponibilite);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Aucune séance ne correspond au jour choisi.'
        );

        $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            1,
            'lundi'
        );
    }

    public function testCreerReservationsAvecSucces(): void
    {
        $student = $this->createStub(Student::class);
        $formation = $this->createStub(Formation::class);
        $formateur = $this->createStub(Formateur::class);
        $disponibilite = $this->createStub(Disponibilite::class);

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

    public function testCreerReservationsRefuseUnCreneauDejaReserve(): void
    {
        $disponibilite = $this->createStub(Disponibilite::class);
        $disponibilite->method('getFormation')->willReturn($this->createStub(Formation::class));
        $disponibilite->method('getFormateur')->willReturn($this->createStub(Formateur::class));
        $disponibilite->method('getJoursSemaine')->willReturn(['lundi']);
        $disponibilite->method('getDateDebut')->willReturn(new \DateTime('2026-10-19'));
        $disponibilite->method('getDateFin')->willReturn(new \DateTime('2026-10-26'));
        $disponibilite->method('getHeureDebut')->willReturn(new \DateTime('09:00:00'));

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($disponibilite);

        // Une réservation existe déjà pour ce créneau
        $reservationRepository = $this->createStub(ReservationRepository::class);
        $reservationRepository
            ->method('findOneBy')
            ->willReturn(new Reservation());

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $service = new ReservationService(
            $this->disponibiliteRepository,
            $this->entityManager,
            $reservationRepository,
            'https://meet.jit.si'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vous avez déjà réservé ce créneau.');

        $service->creerReservations(
            $this->createStub(Student::class),
            1,
            'lundi'
        );
    }

    public function testRegrouperParFormation(): void
    {
        $this->attendreAucuneEcriture();
        $this->attendreAucuneLecture();

        $formation1 = $this->createStub(Formation::class);
        $formation2 = $this->createStub(Formation::class);

        $formation1
            ->method('getId')
            ->willReturn(1);

        $formation2
            ->method('getId')
            ->willReturn(2);

        $reservation1 = $this->createStub(Reservation::class);
        $reservation2 = $this->createStub(Reservation::class);
        $reservation3 = $this->createStub(Reservation::class);

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
        $this->attendreAucuneEcriture();
        $this->attendreAucuneLecture();

        $reservation = $this->createStub(Reservation::class);

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
        $this->attendreAucuneEcriture();
        $this->attendreAucuneLecture();

        $resultat = $this->reservationService->regrouperParFormation([]);

        $this->assertSame([], $resultat);
    }

   

}
