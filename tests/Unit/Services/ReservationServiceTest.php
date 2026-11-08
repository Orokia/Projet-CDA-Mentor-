<?php

namespace App\Tests\Unit\Services;

use App\Entity\Disponibilite;
use App\Entity\Formation;
use App\Entity\Formateur;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\Student;
use App\Repository\DisponibiliteRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
class ReservationServiceTest extends TestCase
{
    private DisponibiliteRepository $disponibiliteRepository;
    private EntityManagerInterface $entityManager;
    private Connection $connexion;
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

        // Connexion utilisée pour la transaction. Un test peut la remplacer
        // par un mock pour vérifier commit / rollBack.
        $this->connexion = $this->createStub(Connection::class);
        $this->entityManager
            ->method('getConnection')
            ->willReturnCallback(fn (): Connection => $this->connexion);

        // Injection des dépendances dans le service
        $this->reservationService = new ReservationService(
            $this->disponibiliteRepository,
            $this->entityManager,
            $this->createStub(ReservationRepository::class),
            'https://meet.jit.si'
        );
    }

    // Le service ne doit rien enregistrer en base
    // (getConnection reste autorisé : il ouvre la transaction).
    private function attendreAucuneEcriture(): void
    {
        $this->entityManager
            ->expects($this->never())
            ->method($this->logicalNot($this->identicalTo('getConnection')));
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
            'lundi',
            $this->paiementEffectue()
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
            'lundi',
            $this->paiementEffectue()
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
            ->willReturn(['mardi' => 'disponible']);

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
            'lundi',
            $this->paiementEffectue()
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
            ->willReturn(['funday' => 'disponible']);

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($disponibilite);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Jour sélectionné invalide.');

        $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            1,
            'funday',
            $this->paiementEffectue()
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
            ->willReturn(['lundi' => 'disponible']);

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
            'lundi',
            $this->paiementEffectue()
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
            ->willReturn(['lundi' => 'disponible']);

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
            ' LUNDI ',
            $this->paiementEffectue()
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
        $disponibilite->method('getJoursSemaine')->willReturn(['lundi' => 'disponible']);
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
            'lundi',
            $this->paiementEffectue()
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

    /**
     * Disponibilité simulée : mercredis de 14 h à 15 h 30
     * entre le 4 et le 25 novembre 2026 (quatre mercredis).
     *
     * @param array<string, string> $jours jour => statut
     */
    private function creerDisponibiliteSimulee(
        array $jours = ['mercredi' => 'disponible'],
        string $dateDebut = '2026-11-04',
        string $dateFin = '2026-11-25'
    ): Disponibilite {
        $disponibilite = $this->createStub(Disponibilite::class);
        $disponibilite->method('getFormation')->willReturn($this->createStub(Formation::class));
        $disponibilite->method('getFormateur')->willReturn($this->createStub(Formateur::class));
        $disponibilite->method('getJoursSemaine')->willReturn($jours);
        $disponibilite->method('getDateDebut')->willReturn(new \DateTime($dateDebut));
        $disponibilite->method('getDateFin')->willReturn(new \DateTime($dateFin));
        $disponibilite->method('getHeureDebut')->willReturn(new \DateTime('14:00:00'));
        $disponibilite->method('getHeureFin')->willReturn(new \DateTime('15:30:00'));

        return $disponibilite;
    }

    /**
     * La disponibilité est recherchée une seule fois.
     */
    private function fournirDisponibilite(Disponibilite $disponibilite): void
    {
        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(12, LockMode::PESSIMISTIC_WRITE)
            ->willReturn($disponibilite);
    }

    /**
     * Remplace la connexion par un mock qui note les appels de transaction.
     *
     * @param list<string> $journal
     */
    private function journaliserLaTransaction(array &$journal): Connection
    {
        // Stub : le journal, comparé dans chaque test, sert de vérification
        $connexion = $this->createStub(Connection::class);
        $connexion->method('isTransactionActive')->willReturn(true);

        foreach (['beginTransaction', 'commit', 'rollBack'] as $methode) {
            $connexion
                ->method($methode)
                ->willReturnCallback(static function () use (&$journal, $methode): void {
                    $journal[] = $methode;
                });
        }

        $this->connexion = $connexion;

        return $connexion;
    }

    /**
     * Un flush avant et un après la génération des liens.
     */
    private function attendreDeuxFlush(): void
    {
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');
    }

    /**
     * Simule l'identifiant attribué par la base au premier flush.
     */
    private function simulerIdentifiantsAuPersist(): void
    {
        $prochainId = 101;

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(static function (object $reservation) use (&$prochainId): void {
                (new \ReflectionProperty(Reservation::class, 'id'))
                    ->setValue($reservation, $prochainId++);
            });
    }

    /**
     * Paiement effectué : seule condition pour confirmer une réservation.
     */
    private function paiementEffectue(): Paiement
    {
        return (new Paiement())->setStatut(Paiement::STATUT_PAYE);
    }

    /**
     * Une réservation n'est confirmée que si le paiement est effectué :
     * sans paiement payé, rien n'est enregistré et la transaction est annulée.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('paiementsNonEffectues')]
    public function testCreerReservationsRefuseUnPaiementNonEffectue(string $statut): void
    {
        $journal = [];
        $this->journaliserLaTransaction($journal);
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee());
        $this->attendreAucuneEcriture();

        $paiement = (new Paiement())->setStatut($statut);

        try {
            $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $paiement);
            self::fail('Une réservation ne doit pas être confirmée sans paiement effectué.');
        } catch (\LogicException $exception) {
            self::assertSame('Une réservation ne peut être confirmée qu\'après le paiement.', $exception->getMessage());
        }

        self::assertSame(['beginTransaction', 'rollBack'], $journal);
        self::assertCount(0, $paiement->getReservations());
    }

    public static function paiementsNonEffectues(): iterable
    {
        yield 'en attente' => [Paiement::STATUT_EN_ATTENTE];
        yield 'annulé' => [Paiement::STATUT_ANNULE];
        yield 'remboursé' => [Paiement::STATUT_REMBOURSE];
    }

    public function testCreerReservationsConfirmeChaqueSeanceSurLePaiementEffectue(): void
    {
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee());
        $this->simulerIdentifiantsAuPersist();
        $this->attendreDeuxFlush();

        $paiement = $this->paiementEffectue();

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            'mercredi',
            $paiement
        );

        // Quatre mercredis du 4 au 25 novembre 2026
        self::assertCount(4, $reservations);
        self::assertCount(4, $paiement->getReservations());

        foreach ($reservations as $reservation) {
            self::assertSame(Reservation::STATUT_CONFIRMEE, $reservation->getStatut());
            self::assertSame($paiement, $reservation->getPaiement());
        }
    }

    public function testCreerReservationsGenereUnLienJitsiUniqueParSeance(): void
    {
        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(12)
            ->willReturn($this->creerDisponibiliteSimulee());
        $this->simulerIdentifiantsAuPersist();

        // La barre oblique finale du domaine ne doit pas être doublée
        $service = new ReservationService(
            $this->disponibiliteRepository,
            $this->entityManager,
            $this->createStub(ReservationRepository::class),
            'https://meet.jit.si/'
        );

        $reservations = $service->creerReservations(
            $this->createStub(Student::class),
            12,
            'mercredi',
            $this->paiementEffectue()
        );

        $this->assertCount(4, $reservations);

        foreach ($reservations as $reservation) {
            $this->assertMatchesRegularExpression(
                '#^https://meet\.jit\.si/Mentor-Reservation-' . $reservation->getId() . '-[0-9a-f]{16}$#',
                $reservation->getLienVisio()
            );
        }

        $liens = array_map(
            static fn (Reservation $reservation): string => $reservation->getLienVisio(),
            $reservations
        );
        $this->assertCount(4, array_unique($liens), 'Chaque séance doit avoir sa propre salle.');
    }

    public function testCreerReservationsUtiliseLeDomaineJitsiConfigure(): void
    {
        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(12)
            ->willReturn($this->creerDisponibiliteSimulee(dateFin: '2026-11-04'));
        $this->simulerIdentifiantsAuPersist();

        $service = new ReservationService(
            $this->disponibiliteRepository,
            $this->entityManager,
            $this->createStub(ReservationRepository::class),
            'https://visio.mentor-formation.fr'
        );

        $reservations = $service->creerReservations(
            $this->createStub(Student::class),
            12,
            'mercredi',
            $this->paiementEffectue()
        );

        $this->assertStringStartsWith(
            'https://visio.mentor-formation.fr/Mentor-Reservation-101-',
            $reservations[0]->getLienVisio()
        );
    }

    public function testCreerReservationsAccepteDesJoursEnregistresAvecMajuscules(): void
    {
        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(12)
            ->willReturn($this->creerDisponibiliteSimulee([' Lundi' => 'disponible', 'MERCREDI ' => ' Disponible ']));

        $this->entityManager
            ->expects($this->exactly(4))
            ->method('persist');

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            'Mercredi',
            $this->paiementEffectue()
        );

        $this->assertCount(4, $reservations);
        $this->assertSame('2026-11-04', $reservations[0]->getDateHeureDebut()->format('Y-m-d'));
        $this->assertSame('2026-11-25', $reservations[3]->getDateHeureDebut()->format('Y-m-d'));
    }

    public function testCreerReservationsSurUnePeriodeDUnSeulJour(): void
    {
        // Le mercredi 4 novembre 2026 est à la fois le début et la fin de la période
        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(12)
            ->willReturn($this->creerDisponibiliteSimulee(dateFin: '2026-11-04'));

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            'mercredi',
            $this->paiementEffectue()
        );

        $this->assertCount(1, $reservations);
        $this->assertSame(
            '2026-11-04 14:00 → 2026-11-04 15:30',
            $reservations[0]->getDateHeureDebut()->format('Y-m-d H:i')
                . ' → ' . $reservations[0]->getDateHeureFin()->format('Y-m-d H:i')
        );
    }

    public function testCreerReservationsRechercheUnDoublonSurLaPremiereSeance(): void
    {
        $student = $this->createStub(Student::class);
        $disponibilite = $this->creerDisponibiliteSimulee();

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(12)
            ->willReturn($disponibilite);

        $reservationRepository = $this->createMock(ReservationRepository::class);
        $reservationRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->callback(
                static function (array $criteres) use ($student, $disponibilite): bool {
                    return $criteres['student'] === $student
                        && $criteres['disponibilite'] === $disponibilite
                        && $criteres['dateHeureDebut']->format('Y-m-d H:i:s') === '2026-11-04 14:00:00';
                }
            ))
            ->willReturn(null);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $service = new ReservationService(
            $this->disponibiliteRepository,
            $this->entityManager,
            $reservationRepository,
            'https://meet.jit.si'
        );

        $this->assertCount(4, $service->creerReservations($student, 12, 'mercredi', $this->paiementEffectue()));
    }

    // ------------------------------------------------------------------
    // Cas non passants
    // ------------------------------------------------------------------

    public function testCreerReservationsRefuseUnePeriodeInversee(): void
    {
        $this->attendreAucuneEcriture();

        // Date de fin antérieure à la date de début : aucune date à parcourir
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(
            dateDebut: '2026-11-25',
            dateFin: '2026-11-04'
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucune séance ne correspond au jour choisi.');

        $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
    }

    public function testCreerReservationsRefuseUneDisponibiliteSansJour(): void
    {
        $this->attendreAucuneEcriture();

        $this->fournirDisponibilite($this->creerDisponibiliteSimulee([]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le jour choisi n'est pas disponible.");

        $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
    }

    public function testCreerReservationsRefuseUnJourCompose(): void
    {
        $this->attendreAucuneEcriture();

        $this->fournirDisponibilite($this->creerDisponibiliteSimulee());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le jour choisi n'est pas disponible.");

        // Seule une égalité stricte après normalisation est acceptée
        $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi,jeudi', $this->paiementEffectue());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function statutsNonReservables(): iterable
    {
        yield 'plus disponible' => ['plus disponible'];
        yield 'plus disponible avec majuscules et espaces' => [' Plus Disponible '];
        yield 'statut inconnu' => ['complet'];
        yield 'statut vide' => [''];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('statutsNonReservables')]
    public function testCreerReservationsRefuseUnJourQuiNEstPlusDisponible(string $statut): void
    {
        $this->attendreAucuneEcriture();

        $this->fournirDisponibilite($this->creerDisponibiliteSimulee([
            'lundi' => 'disponible',
            'mercredi' => $statut,
        ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ce jour n'est plus disponible.");

        $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
    }

    public function testCreerReservationsAccepteUnJourDisponibleAvecDAutresJoursPlusDisponibles(): void
    {
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee([
            'lundi' => 'plus disponible',
            'mercredi' => 'disponible',
        ]));
        $this->attendreDeuxFlush();

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            'mercredi',
            $this->paiementEffectue()
        );

        $this->assertCount(4, $reservations);
    }

    /**
     * Vraie entité (pas un stub) : permet de vérifier la mise à jour du statut.
     *
     * @param array<string, string> $jours jour => statut
     */
    private function creerDisponibiliteReelle(array $jours): Disponibilite
    {
        return (new Disponibilite())
            ->setFormation($this->createStub(Formation::class))
            ->setFormateur($this->createStub(Formateur::class))
            ->setJoursSemaine($jours)
            ->setDateDebut(new \DateTime('2026-11-04'))
            ->setDateFin(new \DateTime('2026-11-25'))
            ->setHeureDebut(new \DateTime('14:00:00'))
            ->setHeureFin(new \DateTime('15:30:00'));
    }

    public function testCreerReservationsMarqueLeJourChoisiCommePlusDisponible(): void
    {
        // Clé enregistrée avec majuscule et espace : elle doit être conservée
        $disponibilite = $this->creerDisponibiliteReelle([
            'lundi' => 'disponible',
            'Mercredi ' => 'disponible',
        ]);
        $this->fournirDisponibilite($disponibilite);

        // Le statut doit déjà être changé au premier flush, avec les réservations
        $statutsAuxFlush = [];
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush')
            ->willReturnCallback(static function () use ($disponibilite, &$statutsAuxFlush): void {
                $statutsAuxFlush[] = $disponibilite->getJoursSemaine()['Mercredi '];
            });

        $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'MERCREDI', $this->paiementEffectue());

        self::assertSame(['plus disponible', 'plus disponible'], $statutsAuxFlush);
        self::assertSame(
            ['lundi' => 'disponible', 'Mercredi ' => 'plus disponible'],
            $disponibilite->getJoursSemaine()
        );
    }

    public function testCreerReservationsNeChangePasLeStatutSiLeCreneauEstDejaReserve(): void
    {
        $this->attendreAucuneEcriture();

        $disponibilite = $this->creerDisponibiliteReelle(['mercredi' => 'disponible']);
        $this->fournirDisponibilite($disponibilite);

        $reservationRepository = $this->createStub(ReservationRepository::class);
        $reservationRepository->method('findOneBy')->willReturn(new Reservation());

        $service = new ReservationService(
            $this->disponibiliteRepository,
            $this->entityManager,
            $reservationRepository,
            'https://meet.jit.si'
        );

        try {
            $service->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
            self::fail('Le doublon aurait dû être refusé.');
        } catch (\RuntimeException $exception) {
            // Le doublon est signalé avant le statut, même si le jour n'est plus disponible
            self::assertSame('Vous avez déjà réservé ce créneau.', $exception->getMessage());
        }

        self::assertSame(['mercredi' => 'disponible'], $disponibilite->getJoursSemaine());
    }

    public function testCreerReservationsSignaleLeDoublonAvantLeStatutPlusDisponible(): void
    {
        $this->attendreAucuneEcriture();

        // Cas du double clic : le jour vient d'être marqué « plus disponible »
        // par la première réservation du même étudiant
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(['mercredi' => 'plus disponible']));

        $reservationRepository = $this->createStub(ReservationRepository::class);
        $reservationRepository->method('findOneBy')->willReturn(new Reservation());

        $service = new ReservationService(
            $this->disponibiliteRepository,
            $this->entityManager,
            $reservationRepository,
            'https://meet.jit.si'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vous avez déjà réservé ce créneau.');

        $service->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
    }

    public function testCreerReservationsNeChangePasLeStatutSansSeance(): void
    {
        $this->attendreAucuneEcriture();

        // Aucun mercredi entre le jeudi 5 et le mardi 10 novembre 2026
        $disponibilite = $this->creerDisponibiliteReelle(['mercredi' => 'disponible'])
            ->setDateDebut(new \DateTime('2026-11-05'))
            ->setDateFin(new \DateTime('2026-11-10'));
        $this->fournirDisponibilite($disponibilite);

        try {
            $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
            self::fail('Une période sans séance aurait dû être refusée.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Aucune séance ne correspond au jour choisi.', $exception->getMessage());
        }

        self::assertSame(['mercredi' => 'disponible'], $disponibilite->getJoursSemaine());
    }

    // ------------------------------------------------------------------
    // Transaction et réservations simultanées
    // ------------------------------------------------------------------

    public function testCreerReservationsVerrouilleLaDisponibiliteDansUneTransaction(): void
    {
        $journal = [];
        $this->journaliserLaTransaction($journal);

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->with(12, LockMode::PESSIMISTIC_WRITE)
            ->willReturnCallback(function () use (&$journal): Disponibilite {
                $journal[] = 'find (verrou)';

                return $this->creerDisponibiliteSimulee();
            });

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush')
            ->willReturnCallback(static function () use (&$journal): void {
                $journal[] = 'flush';
            });

        $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());

        // Le verrou est pris dans la transaction, qui est validée après les deux flush
        self::assertSame(
            ['beginTransaction', 'find (verrou)', 'flush', 'flush', 'commit'],
            $journal
        );
    }

    public function testCreerReservationsAnnuleLaTransactionSurUneErreurMetier(): void
    {
        $this->attendreAucuneEcriture();

        $journal = [];
        $this->journaliserLaTransaction($journal);
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(['mercredi' => 'plus disponible']));

        try {
            $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
            self::fail('Un jour plus disponible aurait dû être refusé.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame("Ce jour n'est plus disponible.", $exception->getMessage());
        }

        self::assertSame(['beginTransaction', 'rollBack'], $journal);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function flushEnEchec(): iterable
    {
        yield 'premier flush (réservations + statut)' => [1];
        yield 'second flush (liens visio)' => [2];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('flushEnEchec')]
    public function testCreerReservationsAnnuleToutSiUnFlushEchoue(int $flushEnEchec): void
    {
        $journal = [];
        $this->journaliserLaTransaction($journal);

        $disponibilite = $this->creerDisponibiliteReelle(['mercredi' => 'disponible']);
        $this->fournirDisponibilite($disponibilite);

        $panne = new \Exception('SQLSTATE[HY000] panne simulée');
        $numeroFlush = 0;

        $this->entityManager
            ->expects($this->exactly($flushEnEchec))
            ->method('flush')
            ->willReturnCallback(static function () use (&$numeroFlush, $flushEnEchec, $panne): void {
                if (++$numeroFlush === $flushEnEchec) {
                    throw $panne;
                }
            });

        try {
            $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
            self::fail('La panne aurait dû être propagée.');
        } catch (\Exception $exception) {
            // L'erreur d'origine est relancée telle quelle, pour le contrôleur et les logs
            self::assertSame($panne, $exception);
        }

        // Rien n'est validé : la base annule les réservations et le statut du jour
        self::assertSame(['beginTransaction', 'rollBack'], $journal);
    }

    public function testCreerReservationsNAnnulePasUneTransactionDejaTerminee(): void
    {
        $this->attendreAucuneEcriture();

        // Par exemple après une connexion perdue : plus de transaction active
        $connexion = $this->createMock(Connection::class);
        $connexion->method('isTransactionActive')->willReturn(false);
        $connexion->expects($this->never())->method('rollBack');
        $this->connexion = $connexion;

        $this->disponibiliteRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disponibilité introuvable.');

        $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
    }

    public function testCreerReservationsIgnoreLAncienFormatEnListe(): void
    {
        $this->attendreAucuneEcriture();

        // Ancien format : liste de jours sans statut, les clés sont des entiers
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(['mercredi']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le jour choisi n'est pas disponible.");

        $this->reservationService->creerReservations($this->createStub(Student::class), 12, 'mercredi', $this->paiementEffectue());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function joursNonReconnus(): iterable
    {
        yield 'chaîne vide' => [''];
        yield 'espaces seuls' => ['   '];
        yield 'jour en anglais' => ['wednesday'];
        yield 'abréviation' => ['mer'];
        yield 'jour avec faute' => ['mecredi'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('joursNonReconnus')]
    public function testCreerReservationsRefuseUnJourNonReconnu(string $jour): void
    {
        $this->attendreAucuneEcriture();

        $this->fournirDisponibilite($this->creerDisponibiliteSimulee());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le jour choisi n'est pas disponible.");

        $this->reservationService->creerReservations($this->createStub(Student::class), 12, $jour, $this->paiementEffectue());
    }

    // ------------------------------------------------------------------
    // Cas limites
    // ------------------------------------------------------------------

    /**
     * Semaine du lundi 2 au dimanche 8 novembre 2026 :
     * chaque jour, du premier (1) au dernier (7) numéro ISO, apparaît une fois.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function joursDeLaSemaine(): iterable
    {
        yield 'lundi (ISO 1, borne basse)' => ['lundi', '2026-11-02'];
        yield 'mardi' => ['mardi', '2026-11-03'];
        yield 'mercredi' => ['mercredi', '2026-11-04'];
        yield 'jeudi' => ['jeudi', '2026-11-05'];
        yield 'vendredi' => ['vendredi', '2026-11-06'];
        yield 'samedi' => ['samedi', '2026-11-07'];
        yield 'dimanche (ISO 7, borne haute)' => ['dimanche', '2026-11-08'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('joursDeLaSemaine')]
    public function testCreerReservationsAssocieChaqueJourASaDate(string $jour, string $dateAttendue): void
    {
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee([$jour => 'disponible'], '2026-11-02', '2026-11-08'));
        $this->attendreDeuxFlush();

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            $jour,
            $this->paiementEffectue()
        );

        $this->assertCount(1, $reservations);
        $this->assertSame($dateAttendue, $reservations[0]->getDateHeureDebut()->format('Y-m-d'));
    }

    public function testCreerReservationsInclutLesDeuxBornesDeLaPeriode(): void
    {
        // Du dimanche 1er au dimanche 8 novembre 2026 : les deux bornes sont des dimanches
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(['dimanche' => 'disponible'], '2026-11-01', '2026-11-08'));
        $this->attendreDeuxFlush();

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            'dimanche',
            $this->paiementEffectue()
        );

        $this->assertCount(2, $reservations);
        $this->assertSame('2026-11-01', $reservations[0]->getDateHeureDebut()->format('Y-m-d'));
        $this->assertSame('2026-11-08', $reservations[1]->getDateHeureDebut()->format('Y-m-d'));
    }

    public function testCreerReservationsIgnoreLHeureEnregistreeDansLesDates(): void
    {
        // Une heure parasite dans la date de début ne doit pas exclure le premier jour
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(
            dateDebut: '2026-11-04 23:59:59',
            dateFin: '2026-11-04'
        ));
        $this->attendreDeuxFlush();

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            'mercredi',
            $this->paiementEffectue()
        );

        $this->assertCount(1, $reservations);
        $this->assertSame('2026-11-04 14:00', $reservations[0]->getDateHeureDebut()->format('Y-m-d H:i'));
    }

    public function testCreerReservationsSurUneAnneeComplete(): void
    {
        // 2026 commence et finit un jeudi : 53 jeudis
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(['jeudi' => 'disponible'], '2026-01-01', '2026-12-31'));

        $this->entityManager
            ->expects($this->exactly(53))
            ->method('persist');

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            'jeudi',
            $this->paiementEffectue()
        );

        $this->assertCount(53, $reservations);
        $this->assertSame('2026-01-01', $reservations[0]->getDateHeureDebut()->format('Y-m-d'));
        $this->assertSame('2026-12-31', $reservations[52]->getDateHeureDebut()->format('Y-m-d'));
    }

    public function testCreerReservationsAChevalSurDeuxAnnees(): void
    {
        // Du lundi 28 décembre 2026 au lundi 4 janvier 2027
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(['lundi' => 'disponible'], '2026-12-28', '2027-01-04'));
        $this->attendreDeuxFlush();

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            'lundi',
            $this->paiementEffectue()
        );

        $this->assertCount(2, $reservations);
        $this->assertSame('2026-12-28', $reservations[0]->getDateHeureDebut()->format('Y-m-d'));
        $this->assertSame('2027-01-04', $reservations[1]->getDateHeureDebut()->format('Y-m-d'));
    }

    public function testCreerReservationsLe29FevrierDUneAnneeBissextile(): void
    {
        // Le 29 février 2028 est un mardi
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(['mardi' => 'disponible'], '2028-02-28', '2028-03-01'));
        $this->attendreDeuxFlush();

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            'mardi',
            $this->paiementEffectue()
        );

        $this->assertCount(1, $reservations);
        $this->assertSame('2028-02-29 14:00', $reservations[0]->getDateHeureDebut()->format('Y-m-d H:i'));
    }

    public function testCreerReservationsAccepteUnJourEntoureDeTabulations(): void
    {
        // trim() retire aussi tabulations et retours à la ligne
        $this->fournirDisponibilite($this->creerDisponibiliteSimulee(['mercredi' => 'disponible']));
        $this->attendreDeuxFlush();

        $reservations = $this->reservationService->creerReservations(
            $this->createStub(Student::class),
            12,
            "\tMERCREDI\n",
            $this->paiementEffectue()
        );

        $this->assertCount(4, $reservations);
    }

    public function testCreerReservationsAuPassageALHeureDHiver(): void
    {
        // Le changement d'heure (25 octobre 2026) ne décale pas les séances
        $fuseauInitial = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        try {
            $this->fournirDisponibilite($this->creerDisponibiliteSimulee(['samedi' => 'disponible', 'lundi' => 'disponible'], '2026-10-24', '2026-10-26'));
            $this->attendreDeuxFlush();

            $reservations = $this->reservationService->creerReservations(
                $this->createStub(Student::class),
                12,
                'lundi',
                $this->paiementEffectue()
            );
        } finally {
            date_default_timezone_set($fuseauInitial);
        }

        $this->assertCount(1, $reservations);
        $this->assertSame('2026-10-26 14:00', $reservations[0]->getDateHeureDebut()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-26 15:30', $reservations[0]->getDateHeureFin()->format('Y-m-d H:i'));
    }
}
