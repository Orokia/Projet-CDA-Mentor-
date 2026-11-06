<?php

namespace App\Tests\Integration\Services;

use App\Entity\Disponibilite;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\Student;
use App\Service\PaiementService;
use App\Service\ReservationService;
use App\Tests\Support\EntitesDeTestTrait;
use App\Tests\Support\Paiement\PasserellePaiementMock;
use Omnipay\Common\Exception\InvalidCreditCardException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tunnel de paiement réservation → paiement → confirmation, sur une vraie base (SQLite),
 * avec la passerelle Omnipay Mock à la place de Stripe Checkout.
 */
final class PaiementServiceIntegrationTest extends KernelTestCase
{
    use EntitesDeTestTrait;

    private const CARTE_VALIDE = '4242 4242 4242 4242';
    private const CVC = '123';

    private PaiementService $paiementService;
    private PasserellePaiementMock $passerelle;

    protected function setUp(): void
    {
        self::bootKernel();
        PasserellePaiementMock::reinitialiser();

        $this->paiementService = static::getContainer()->get(PaiementService::class);
        $this->passerelle = static::getContainer()->get(PasserellePaiementMock::class);
    }

    /**
     * Disponibilité du lundi 19 au lundi 26 octobre 2026 (deux lundis), formation à 199,99 €.
     */
    private function creerCreneau(array $jours = ['lundi' => 'disponible', 'mardi' => 'disponible']): Disponibilite
    {
        return $this->creerDisponibilite($this->creerFormateur(), $this->creerFormation(), $jours);
    }

    /**
     * Étapes 1 à 3 : choix du créneau, redirection vers la page de paiement.
     *
     * @return array{0: Paiement, 1: string} paiement en attente et identifiant de la session
     */
    private function demarrer(Student $student, Disponibilite $disponibilite, string $jour = 'lundi'): array
    {
        $url = $this->paiementService->demarrerPaiement($student, $disponibilite->getId(), $jour);
        $sessionId = PasserellePaiementMock::idDepuisUrl($url);

        $paiement = $this->em()->getRepository(Paiement::class)->findOneBy(['sessionCheckoutId' => $sessionId]);
        self::assertNotNull($paiement);

        return [$paiement, $sessionId];
    }

    private function relireDisponibilite(int $id): Disponibilite
    {
        $this->em()->clear();

        return $this->em()->find(Disponibilite::class, $id);
    }

    private function nombre(string $classe): int
    {
        return $this->em()->getRepository($classe)->count([]);
    }

    public function testTunnelCompletAvecLaCarteDeTest4242(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        $disponibilite = $this->creerCreneau();

        // Réservation : le paiement est « en attente », aucune réservation n'existe encore
        [$paiement, $sessionId] = $this->demarrer($student, $disponibilite);

        self::assertSame(Paiement::STATUT_EN_ATTENTE, $paiement->getStatut());
        self::assertSame(0, $this->nombre(Reservation::class));
        self::assertSame('disponible', $disponibilite->getJoursSemaine()['lundi']);

        // Paiement : 4242 4242 4242 4242, date future, CVC 123
        $reponse = $this->passerelle->payer($sessionId, PasserellePaiementMock::carte(self::CARTE_VALIDE, cvc: self::CVC));
        self::assertTrue($reponse->isSuccessful(), $reponse->getMessage() ?? '');

        // Retour sur Mentor : vérification puis confirmation
        $this->paiementService->confirmerPaiement($student, $paiement->getId());

        // Relecture en base de ce qui a vraiment été écrit
        $this->em()->clear();
        $paiementEnBase = $this->em()->find(Paiement::class, $paiement->getId());

        self::assertSame(Paiement::STATUT_PAYE, $paiementEnBase->getStatut());
        self::assertSame('199.99', $paiementEnBase->getMontant());
        self::assertSame('eur', $paiementEnBase->getDevise());
        self::assertSame(Paiement::MODE_STRIPE_CHECKOUT, $paiementEnBase->getMode());
        self::assertStringStartsWith('pi_mock_', $paiementEnBase->getReferencePaiement());
        self::assertNotNull($paiementEnBase->getDatePaiement());

        // Une réservation par lundi, rattachée au paiement, avec son lien Jitsi
        $reservations = $paiementEnBase->getReservations()->toArray();
        self::assertCount(2, $reservations);

        foreach ($reservations as $reservation) {
            self::assertSame('confirmée', $reservation->getStatut());
            self::assertSame($student->getId(), $reservation->getStudent()->getId());
            self::assertMatchesRegularExpression(
                '#^https://meet\.jit\.si/Mentor-Reservation-'.$reservation->getId().'-[0-9a-f]{16}$#',
                $reservation->getLienVisio()
            );
        }

        // Le jour réservé n'est plus proposé aux autres étudiants
        self::assertSame(
            ['lundi' => 'plus disponible', 'mardi' => 'disponible'],
            $this->relireDisponibilite($disponibilite->getId())->getJoursSemaine()
        );
    }

    public function testLaSessionStripeReprendLeMontantEtLesUrlsDeRetour(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        $avant = new \DateTimeImmutable();

        [$paiement, $sessionId] = $this->demarrer($student, $this->creerCreneau());
        $demande = PasserellePaiementMock::demande($sessionId);

        self::assertSame(19999, $demande->montantCentimes);
        self::assertSame('eur', $demande->devise);
        self::assertSame('Symfony', $demande->libelle);
        self::assertSame((string) $paiement->getId(), $demande->referenceInterne);
        self::assertSame('amadou@example.test', $demande->emailClient);
        self::assertSame('http://localhost/api/stripe/webhook/paiement/'.$paiement->getId().'/succes', $demande->urlSucces);
        self::assertSame('http://localhost/api/stripe/webhook/paiement/'.$paiement->getId().'/annule', $demande->urlAnnulation);

        // Stripe exige une expiration à au moins 30 minutes
        self::assertGreaterThanOrEqual($avant->modify('+30 minutes'), $demande->expireA);
    }

    public function testUneCarteRefuseeNeCreeAucuneReservation(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        $disponibilite = $this->creerCreneau();
        [$paiement, $sessionId] = $this->demarrer($student, $disponibilite);

        $reponse = $this->passerelle->payer($sessionId, PasserellePaiementMock::carte('4000 0000 0000 0002', cvc: self::CVC));
        self::assertFalse($reponse->isSuccessful());
        self::assertSame('Votre carte a été refusée.', $reponse->getMessage());

        try {
            $this->paiementService->confirmerPaiement($student, $paiement->getId());
            self::fail('Un paiement non validé ne doit pas être confirmé.');
        } catch (\RuntimeException $exception) {
            self::assertSame("Le paiement n'a pas été validé par Stripe. Aucune réservation n'a été créée.", $exception->getMessage());
        }

        self::assertSame(0, $this->nombre(Reservation::class));
        self::assertSame(Paiement::STATUT_EN_ATTENTE, $this->em()->find(Paiement::class, $paiement->getId())->getStatut());
        self::assertSame('disponible', $this->relireDisponibilite($disponibilite->getId())->getJoursSemaine()['lundi']);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function cartesInvalides(): iterable
    {
        yield 'carte expirée' => [self::CARTE_VALIDE, '01/20', self::CVC, 'Card has expired'];
        yield 'numéro faux (Luhn)' => ['4242 4242 4242 4241', '12/40', self::CVC, 'Card number is invalid'];
        yield 'CVC à deux chiffres' => [self::CARTE_VALIDE, '12/40', '12', 'Le cryptogramme visuel (CVC) est invalide.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cartesInvalides')]
    public function testUneCarteInvalideEstRefuseeParLaPasserelle(string $numero, string $expiration, string $cvc, string $message): void
    {
        [, $sessionId] = $this->demarrer($this->creerUtilisateur()->getStudent(), $this->creerCreneau());

        $this->expectException(InvalidCreditCardException::class);
        $this->expectExceptionMessage($message);

        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte($numero, $expiration, $cvc));
    }

    public function testLaConfirmationNeCreeLesReservationsQuUneFois(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        [$paiement, $sessionId] = $this->demarrer($student, $this->creerCreneau());
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());

        // Page de retour rechargée
        $this->paiementService->confirmerPaiement($student, $paiement->getId());
        $this->paiementService->confirmerPaiement($student, $paiement->getId());

        self::assertSame(2, $this->nombre(Reservation::class));
    }

    public function testLAnnulationFermeLaSessionSansRienReserver(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        $disponibilite = $this->creerCreneau();
        [$paiement, $sessionId] = $this->demarrer($student, $disponibilite);

        $resultat = $this->paiementService->annulerPaiement($student, $paiement->getId());

        self::assertSame(Paiement::STATUT_ANNULE, $resultat->getStatut());
        self::assertTrue(PasserellePaiementMock::estExpiree($sessionId));
        self::assertSame(0, $this->nombre(Reservation::class));
        self::assertSame('disponible', $this->relireDisponibilite($disponibilite->getId())->getJoursSemaine()['lundi']);
    }

    public function testUnPaiementEffectueAvantLAnnulationEstConfirme(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        [$paiement, $sessionId] = $this->demarrer($student, $this->creerCreneau());

        // Payé dans un autre onglet, puis lien « annuler » de la première page
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());
        $resultat = $this->paiementService->annulerPaiement($student, $paiement->getId());

        self::assertTrue($resultat->estPaye());
        self::assertSame(2, $this->nombre(Reservation::class));
    }

    public function testUnCreneauPrisPendantLePaiementEstRembourse(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        $disponibilite = $this->creerCreneau();
        [$paiement, $sessionId] = $this->demarrer($student, $disponibilite);
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());

        // Pendant le paiement, le lundi est réservé par ailleurs (par exemple depuis l'administration)
        static::getContainer()->get(ReservationService::class)->creerReservations(
            $this->creerUtilisateur('autre.etudiant@example.test')->getStudent(),
            $disponibilite->getId(),
            'lundi',
            $this->paiementEffectue()
        );

        try {
            $this->paiementService->confirmerPaiement($student, $paiement->getId());
            self::fail('Le créneau pris aurait dû être refusé.');
        } catch (\RuntimeException $exception) {
            self::assertSame(
                "Ce jour n'est plus disponible. Votre paiement a été intégralement remboursé.",
                $exception->getMessage()
            );
        }

        $this->em()->clear();
        $paiementEnBase = $this->em()->find(Paiement::class, $paiement->getId());

        self::assertSame(Paiement::STATUT_REMBOURSE, $paiementEnBase->getStatut());
        self::assertSame([$paiementEnBase->getReferencePaiement()], PasserellePaiementMock::remboursements());
        self::assertCount(0, $paiementEnBase->getReservations());

        // Un nouveau retour sur la page ne rembourse pas une seconde fois
        try {
            $this->paiementService->confirmerPaiement($student, $paiement->getId());
        } catch (\RuntimeException) {
        }
        self::assertCount(1, PasserellePaiementMock::remboursements());
    }

    public function testUnAutreEtudiantNePeutPasPayerLeJourEnCoursDePaiement(): void
    {
        $disponibilite = $this->creerCreneau();
        $this->demarrer($this->creerUtilisateur()->getStudent(), $disponibilite);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ce jour est en cours de réservation par un autre étudiant. Veuillez réessayer dans quelques minutes.');

        $this->paiementService->demarrerPaiement(
            $this->creerUtilisateur('autre.etudiant@example.test')->getStudent(),
            $disponibilite->getId(),
            'lundi'
        );
    }

    public function testUnPaiementExpireNeBloquePlusLeJour(): void
    {
        $disponibilite = $this->creerCreneau();
        [$paiement] = $this->demarrer($this->creerUtilisateur()->getStudent(), $disponibilite);

        // Session Stripe expirée : créée il y a plus de 31 minutes
        $paiement->setDateCreation(new \DateTimeImmutable('-32 minutes'));
        $this->em()->flush();

        $url = $this->paiementService->demarrerPaiement(
            $this->creerUtilisateur('autre.etudiant@example.test')->getStudent(),
            $disponibilite->getId(),
            'lundi'
        );

        self::assertStringStartsWith(PasserellePaiementMock::URL_PAGE_PAIEMENT, $url);
    }

    public function testUnCreneauInvalideNeCreeNiPaiementNiSession(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        $disponibilite = $this->creerCreneau(['lundi' => 'disponible', 'mardi' => 'plus disponible']);

        try {
            $this->paiementService->demarrerPaiement($student, $disponibilite->getId(), 'mardi');
            self::fail('Un jour plus disponible ne doit pas pouvoir être payé.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame("Ce jour n'est plus disponible.", $exception->getMessage());
        }

        self::assertSame(0, $this->nombre(Paiement::class));
    }

    public function testUnePanneStripeAuDemarrageNeLaissePasDePaiement(): void
    {
        PasserellePaiementMock::simulerPanne();

        try {
            $this->paiementService->demarrerPaiement($this->creerUtilisateur()->getStudent(), $this->creerCreneau()->getId(), 'lundi');
            self::fail('La panne aurait dû être propagée.');
        } catch (\Exception $exception) {
            self::assertSame('Stripe injoignable (panne simulée).', $exception->getMessage());
        }

        // Transaction annulée : pas de paiement « en attente » orphelin
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM paiement'));
    }

    public function testUnMontantIncoherentNeConfirmePasLaReservation(): void
    {
        $student = $this->creerUtilisateur()->getStudent();
        [$paiement, $sessionId] = $this->demarrer($student, $this->creerCreneau());
        $this->passerelle->payer($sessionId, PasserellePaiementMock::carte());

        // Montant modifié après la création de la session Stripe
        $paiement->setMontant('1.00');
        $this->em()->flush();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le paiement reçu ne correspond pas à cette réservation. Contactez le support Mentor.');

        $this->paiementService->confirmerPaiement($student, $paiement->getId());
    }

    public function testLePaiementDUnAutreEtudiantEstIntrouvable(): void
    {
        [$paiement] = $this->demarrer($this->creerUtilisateur()->getStudent(), $this->creerCreneau());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Paiement introuvable.');

        $this->paiementService->confirmerPaiement(
            $this->creerUtilisateur('autre.etudiant@example.test')->getStudent(),
            $paiement->getId()
        );
    }
}
