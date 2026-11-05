<?php

namespace App\Tests\Integration\Services;

use App\Entity\Disponibilite;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use App\Tests\Support\EntitesDeTestTrait;
use Doctrine\ORM\Events;
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
            'lundi',
            $this->paiementEffectue()
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

        $this->reservationService->creerReservations($student, $disponibilite->getId(), 'lundi', $this->paiementEffectue());

        // La recherche du doublon (findOneBy sur un DateTime) doit fonctionner en vraie base
        try {
            $this->reservationService->creerReservations($student, $disponibilite->getId(), 'lundi', $this->paiementEffectue());
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
            'lundi',
            $this->paiementEffectue()
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
            'lundi',
            $this->paiementEffectue()
        );

        try {
            $this->reservationService->creerReservations(
                $this->creerUtilisateur('autre.etudiant@example.test')->getStudent(),
                $disponibilite->getId(),
                'lundi',
                $this->paiementEffectue()
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

    public function testUnePanneAuSecondFlushNeLaisseRienEnBase(): void
    {
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation()
        );
        $student = $this->creerUtilisateur()->getStudent();

        // Panne simulée à l'enregistrement des liens visio (second flush),
        // après que le premier flush a écrit les réservations et le statut
        $nombreDeFlush = 0;
        $this->em()->getEventManager()->addEventListener(
            Events::onFlush,
            new class ($nombreDeFlush) {
                public function __construct(private int &$nombreDeFlush)
                {
                }

                public function onFlush(): void
                {
                    if (++$this->nombreDeFlush === 2) {
                        throw new \Exception('SQLSTATE[HY000] panne simulée');
                    }
                }
            }
        );

        try {
            $this->reservationService->creerReservations($student, $disponibilite->getId(), 'lundi', $this->paiementEffectue());
            self::fail('La panne aurait dû être propagée.');
        } catch (\Exception $exception) {
            self::assertSame('SQLSTATE[HY000] panne simulée', $exception->getMessage());
        }

        self::assertSame(2, $nombreDeFlush);

        // Lecture directe en base : l'EntityManager est fermé après l'échec du flush
        $connexion = $this->em()->getConnection();

        self::assertSame(0, (int) $connexion->fetchOne('SELECT COUNT(*) FROM reservation'));
        self::assertSame(
            ['lundi' => 'disponible'],
            json_decode(
                $connexion->fetchOne('SELECT jours_semaine FROM disponibilite WHERE id = ?', [$disponibilite->getId()]),
                true
            )
        );
    }

    public function testLeStatutEstReluEnBaseAuMomentDeLaReservation(): void
    {
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation()
        );
        $id = $disponibilite->getId();

        // Un autre étudiant vient de réserver ce lundi, dans une autre requête :
        // seule la base est à jour, pas l'entité chargée ici
        $this->em()->getConnection()->executeStatement(
            'UPDATE disponibilite SET jours_semaine = ? WHERE id = ?',
            [json_encode(['lundi' => 'plus disponible']), $id]
        );
        $this->em()->clear();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ce jour n'est plus disponible.");

        $this->reservationService->creerReservations($this->creerUtilisateur()->getStudent(), $id, 'lundi', $this->paiementEffectue());
    }

    public function testDisponibiliteIntrouvableDeclencheUneException(): void
    {
        $student = $this->creerUtilisateur()->getStudent();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disponibilité introuvable.');

        $this->reservationService->creerReservations($student, 999999, 'lundi', $this->paiementEffectue());
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
            'lundi',
            $this->paiementEffectue()
        );
    }
}
