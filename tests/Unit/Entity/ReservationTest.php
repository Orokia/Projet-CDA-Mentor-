<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Paiement;
use App\Entity\Reservation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * Une réservation n'a le statut « confirmée » que lorsque son paiement est effectué.
 */
class ReservationTest extends TestCase
{
    public function testConfirmerAvecUnPaiementEffectue(): void
    {
        $paiement = (new Paiement())->setStatut(Paiement::STATUT_PAYE);
        $reservation = new Reservation();

        $reservation->confirmer($paiement);

        self::assertSame(Reservation::STATUT_CONFIRMEE, $reservation->getStatut());
        self::assertTrue($reservation->estConfirmee());
        self::assertSame($paiement, $reservation->getPaiement());
        self::assertTrue($paiement->getReservations()->contains($reservation));
    }

    #[DataProvider('paiementsNonEffectues')]
    public function testConfirmerRefuseUnPaiementNonEffectue(string $statut): void
    {
        $paiement = (new Paiement())->setStatut($statut);
        $reservation = (new Reservation())->setStatut(Reservation::STATUT_EN_ATTENTE);

        try {
            $reservation->confirmer($paiement);
            self::fail('La réservation ne doit pas être confirmée sans paiement effectué.');
        } catch (\LogicException $exception) {
            self::assertSame('Une réservation ne peut être confirmée qu\'après le paiement.', $exception->getMessage());
        }

        self::assertSame(Reservation::STATUT_EN_ATTENTE, $reservation->getStatut());
        self::assertFalse($reservation->estConfirmee());
        self::assertNull($reservation->getPaiement());
        self::assertCount(0, $paiement->getReservations());
    }

    public static function paiementsNonEffectues(): iterable
    {
        yield 'en attente' => [Paiement::STATUT_EN_ATTENTE];
        yield 'annulé' => [Paiement::STATUT_ANNULE];
        yield 'remboursé' => [Paiement::STATUT_REMBOURSE];
    }

    public function testEstConfirmeeFauxPourLesAutresStatuts(): void
    {
        foreach ([Reservation::STATUT_EN_ATTENTE, Reservation::STATUT_ANNULEE, Reservation::STATUT_TERMINEE] as $statut) {
            self::assertFalse((new Reservation())->setStatut($statut)->estConfirmee(), $statut);
        }
    }

    // ---------- Validation (formulaires du back-office) ----------

    public function testValidationRefuseUneConfirmationSansPaiement(): void
    {
        $reservation = (new Reservation())->setStatut(Reservation::STATUT_CONFIRMEE);

        $violations = $this->valider($reservation);

        self::assertCount(1, $violations);
        self::assertSame('statut', $violations[0]->getPropertyPath());
        self::assertSame(
            'Une réservation ne peut être confirmée que si son paiement est effectué.',
            $violations[0]->getMessage()
        );
    }

    #[DataProvider('paiementsNonEffectues')]
    public function testValidationRefuseUneConfirmationAvecUnPaiementNonEffectue(string $statut): void
    {
        $reservation = (new Reservation())
            ->setStatut(Reservation::STATUT_CONFIRMEE)
            ->setPaiement((new Paiement())->setStatut($statut));

        self::assertCount(1, $this->valider($reservation));
    }

    public function testValidationAccepteUneConfirmationAvecUnPaiementEffectue(): void
    {
        $reservation = (new Reservation())
            ->setStatut(Reservation::STATUT_CONFIRMEE)
            ->setPaiement((new Paiement())->setStatut(Paiement::STATUT_PAYE));

        self::assertCount(0, $this->valider($reservation));
    }

    public function testValidationAccepteLesAutresStatutsSansPaiement(): void
    {
        foreach ([Reservation::STATUT_EN_ATTENTE, Reservation::STATUT_ANNULEE, Reservation::STATUT_TERMINEE] as $statut) {
            self::assertCount(0, $this->valider((new Reservation())->setStatut($statut)), $statut);
        }
    }

    /**
     * Réservation confirmée avant le paiement en ligne : elle reste valide
     * tant que son statut ne change pas.
     */
    public function testValidationAccepteUneReservationDejaConfirmeeEnBase(): void
    {
        $reservation = (new Reservation())->setStatut(Reservation::STATUT_CONFIRMEE);
        $reservation->memoriserStatutCharge();

        self::assertCount(0, $this->valider($reservation));
    }

    public function testValidationRefuseDeReconfirmerUneReservationChargeeAvecUnAutreStatut(): void
    {
        $reservation = (new Reservation())->setStatut(Reservation::STATUT_ANNULEE);
        $reservation->memoriserStatutCharge();

        $reservation->setStatut(Reservation::STATUT_CONFIRMEE);

        self::assertCount(1, $this->valider($reservation));
    }

    private function valider(Reservation $reservation): \Symfony\Component\Validator\ConstraintViolationListInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($reservation);
    }
}
